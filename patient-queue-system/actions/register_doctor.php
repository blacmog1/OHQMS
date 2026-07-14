<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Doctor Onboarding Endpoint (Admin Only)
 *
 * Route:   POST /actions/register_doctor.php
 * Access:  Authenticated users with role = 'admin' only
 *
 * Security model:
 *   - Session-based role guard (403 if not admin)
 *   - Role is HARDCODED to 'doctor' in the SQL — never accepted from client
 *   - Atomic PDO transaction: users -> doctor tables (ROLLBACK on any failure)
 *   - 100% parameterized queries — no string interpolation in SQL
 *   - department_id validated against the live department table before insert
 *   - Argon2id hashing (bcrypt fallback)
 *   - Raw DB errors go to error_log() only; generic message returned to client
 *   - SQLSTATE 23505 (duplicate email) surfaced as user-friendly 409 Conflict
 */



// ---------------------------------------------------------------------------
// 1. Bootstrap — session must start BEFORE any output
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// 2. Method Guard
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// ---------------------------------------------------------------------------
// 3. Authentication & Authorization Guard
//    Must be logged in AND have exactly the 'admin' role.
//    Return 401 for unauthenticated, 403 for wrong role — prevents
//    attackers from fingerprinting admin endpoints.
// ---------------------------------------------------------------------------
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Admin access required.']);
    exit;
}

// ---------------------------------------------------------------------------
// 4. Input Parsing — JSON body or form-data
// ---------------------------------------------------------------------------
$input       = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
} else {
    $input = $_POST;
}

// Extract and type-cast — role is deliberately NOT read from input
$firstName    = trim((string)($input['first_name']    ?? ''));
$lastName     = trim((string)($input['last_name']     ?? ''));
$email        = trim((string)($input['email']         ?? ''));
$phoneNumber  = trim((string)($input['phone_number']  ?? ''));
$password     = (string)($input['password']           ?? '');
$departmentId = $input['department_id'] ?? null;
$roomNumber   = trim((string)($input['room_number']   ?? ''));  // optional

// ---------------------------------------------------------------------------
// 5. Validation
// ---------------------------------------------------------------------------
$errors = [];

if ($firstName === '') {
    $errors['first_name'] = 'First name is required.';
} elseif (mb_strlen($firstName) > 100) {
    $errors['first_name'] = 'First name must not exceed 100 characters.';
}

if ($lastName === '') {
    $errors['last_name'] = 'Last name is required.';
} elseif (mb_strlen($lastName) > 100) {
    $errors['last_name'] = 'Last name must not exceed 100 characters.';
}

if ($email === '') {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please provide a valid email address.';
} elseif (mb_strlen($email) > 255) {
    $errors['email'] = 'Email address is too long.';
}

if ($phoneNumber === '') {
    $errors['phone_number'] = 'Phone number is required.';
} elseif (!preg_match('/^\+?[\d\s\-]{7,20}$/', $phoneNumber)) {
    $errors['phone_number'] = 'Please provide a valid phone number.';
}

if ($password === '') {
    $errors['password'] = 'A temporary password is required.';
} elseif (mb_strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
} elseif (mb_strlen($password) > 128) {
    $errors['password'] = 'Password must not exceed 128 characters.';
}

if ($departmentId === null || $departmentId === '') {
    $errors['department_id'] = 'Department ID is required.';
} elseif (!filter_var($departmentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    $errors['department_id'] = 'Department ID must be a positive integer.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

$departmentId = (int)$departmentId;

// ---------------------------------------------------------------------------
// 6. Validate department_id exists in the database
//    Prevents a FK violation and gives a clean error to the admin UI.
// ---------------------------------------------------------------------------
try {
    $deptCheck = $pdo->prepare(
        "SELECT department_name FROM department WHERE department_id = :did LIMIT 1"
    );
    $deptCheck->execute([':did' => $departmentId]);
    $department = $deptCheck->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => ['department_id' => "No department found with ID $departmentId."],
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log('OHAQRS register_doctor dept-check PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}

// ---------------------------------------------------------------------------
// 7. Hash the temporary password
//    Argon2id preferred (PHP 7.3+); bcrypt fallback.
// ---------------------------------------------------------------------------
$algo    = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = defined('PASSWORD_ARGON2ID')
    ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
    : ['cost' => 12];

$passwordHash = password_hash($password, $algo, $options);

if ($passwordHash === false) {
    error_log('OHAQRS register_doctor: password_hash() returned false.');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}

// ---------------------------------------------------------------------------
// 8. Atomic Database Transaction
//    Step A: INSERT into users  (role hardcoded to 'doctor')
//    Step B: RETURNING id       (used as the FK for the doctor row)
//    Step C: INSERT into doctor (with department_id + user_id)
//    Any failure rolls back both inserts — database stays consistent.
// ---------------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    // --- 8A. Create identity record in users ---
    $stmtUser = $pdo->prepare(
        "INSERT INTO users (email, password_hash, role, created_at, updated_at)
         VALUES (:email, :password_hash, 'doctor', NOW(), NOW())
         RETURNING id"
    );
    $stmtUser->execute([
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        // role is HARDCODED in SQL — not accepted from $input
    ]);
    $userId = (int)$stmtUser->fetchColumn();

    if ($userId === 0) {
        throw new RuntimeException('Failed to retrieve new user ID after INSERT into users.');
    }

    // --- 8B. Create doctor profile record ---
    $stmtDoctor = $pdo->prepare(
        "INSERT INTO doctor
             (first_name, last_name, department_id, user_id, room_number, status, created_at)
         VALUES
             (:first_name, :last_name, :department_id, :user_id, :room_number, 'available', NOW())"
    );
    $stmtDoctor->execute([
        ':first_name'    => $firstName,
        ':last_name'     => $lastName,
        ':department_id' => $departmentId,
        ':user_id'       => $userId,
        ':room_number'   => ($roomNumber !== '') ? $roomNumber : null,
    ]);

    $pdo->commit();

    // ---------------------------------------------------------------------------
    // 9. Success — return safe summary (no internal IDs or hashes)
    // ---------------------------------------------------------------------------
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => "Doctor account created successfully.",
        'doctor'  => [
            'full_name'       => "$firstName $lastName",
            'email'           => $email,
            'department'      => $department['department_name'],
            'role'            => 'doctor',
        ],
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // SQLSTATE 23505 = unique_violation (duplicate email in users table)
    $sqlState = $e->errorInfo[0] ?? $e->getCode();
    if ($sqlState === '23505') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A user with this email address already exists.',
            'errors'  => ['email' => 'Email is already registered in the system.'],
        ]);
    } else {
        error_log('OHAQRS register_doctor PDOException [' . $sqlState . ']: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'A server error occurred during doctor registration. Please try again.',
        ]);
    }
    exit;

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OHAQRS register_doctor RuntimeException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
