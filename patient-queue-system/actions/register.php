<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Registration Handler (Production)
 *
 * Security model:
 *   - Argon2id password hashing (PHP 7.3+)
 *   - Atomic PDO transaction: inserts users + profile table in one unit of work
 *   - All DB inputs use parameterized prepared statements
 *   - Raw DB errors are never sent to the client; they go to error_log()
 *   - SQLSTATE 23505 (unique violation) is surfaced as a user-friendly duplicate message
 */



// ---------------------------------------------------------------------------
// 1. Bootstrap
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session-config.php';

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
// 3. Input Parsing (JSON body or form-data)
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

// Retrieve and type-cast
$firstName       = trim((string)($input['firstName']       ?? ''));
$lastName        = trim((string)($input['lastName']        ?? ''));
$email           = trim((string)($input['email']           ?? ''));
$phone           = trim((string)($input['phone']           ?? ''));
$password        = (string)($input['password']             ?? '');
$confirmPassword = (string)($input['confirmPassword']      ?? '');
$role            = 'patient'; // Force patient - no role selection for self-registration
$dateOfBirth     = trim((string)($input['dateOfBirth']     ?? ''));   // patients only (YYYY-MM-DD)

// ---------------------------------------------------------------------------
// 4. Validation
// ---------------------------------------------------------------------------
$errors = [];

if ($firstName === '') {
    $errors['firstName'] = 'First name is required.';
} elseif (mb_strlen($firstName) > 100) {
    $errors['firstName'] = 'First name must not exceed 100 characters.';
}

if ($lastName === '') {
    $errors['lastName'] = 'Last name is required.';
} elseif (mb_strlen($lastName) > 100) {
    $errors['lastName'] = 'Last name must not exceed 100 characters.';
}

if ($email === '') {
    $errors['email'] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
} elseif (mb_strlen($email) > 255) {
    $errors['email'] = 'Email address is too long.';
}

if ($phone === '') {
    $errors['phone'] = 'Phone number is required.';
} elseif (!preg_match('/^\+?[\d\s\-]{7,20}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (mb_strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
} elseif (mb_strlen($password) > 128) {
    $errors['password'] = 'Password must not exceed 128 characters.';
}

if ($confirmPassword === '') {
    $errors['confirmPassword'] = 'Please confirm your password.';
} elseif ($password !== $confirmPassword) {
    $errors['confirmPassword'] = 'Passwords do not match.';
}

// Only patients can self-register
if ($role !== 'patient') {
    $errors['role'] = 'Patients only. Doctors and staff are added by administrators.';
}

// Patient-specific: date of birth
if ($role === 'patient' && $dateOfBirth !== '') {
    $dob = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
    if (!$dob || $dob->format('Y-m-d') !== $dateOfBirth) {
        $errors['dateOfBirth'] = 'Date of birth must be in YYYY-MM-DD format.';
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

// ---------------------------------------------------------------------------
// 5. Hash the password with Argon2id (memory-hard, GPU-resistant)
//    Falls back to bcrypt on PHP < 7.3 which lacks PASSWORD_ARGON2ID.
// ---------------------------------------------------------------------------
$algo    = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = defined('PASSWORD_ARGON2ID')
    ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
    : ['cost' => 12];

$passwordHash = password_hash($password, $algo, $options);

if ($passwordHash === false) {
    error_log('OHAQRS: password_hash() returned false during registration.');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}

// ---------------------------------------------------------------------------
// 6. Database Transaction: users -> profile table
// ---------------------------------------------------------------------------
try {
    $pdo->beginTransaction();

    // --- 6a. Insert into `users` ---
    $stmtUser = $pdo->prepare(
        "INSERT INTO users (email, password_hash, role, created_at, updated_at)
         VALUES (:email, :password_hash, :role, NOW(), NOW())
         RETURNING id"
    );
    $stmtUser->execute([
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        ':role'          => $role,
    ]);
    $userId = (int) $stmtUser->fetchColumn();

    if ($userId === 0) {
        throw new RuntimeException('Failed to retrieve new user ID after INSERT.');
    }

    // --- 6b. Insert profile data into the role-specific table ---
    if ($role === 'patient') {
        $dobValue = ($dateOfBirth !== '') ? $dateOfBirth : null;
        $stmtProfile = $pdo->prepare(
            "INSERT INTO patient (first_name, last_name, email, phone_number, date_of_birth, user_id, created_at)
             VALUES (:first_name, :last_name, :email, :phone, :dob, :user_id, NOW())"
        );
        $stmtProfile->execute([
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':email'      => $email,
            ':phone'      => $phone,
            ':dob'        => $dobValue,
            ':user_id'    => $userId,
        ]);
    }
    // admin / receptionist: users table entry is sufficient

    $pdo->commit();

    // ---------------------------------------------------------------------------
    // 7. Success response - include user data for auto-login
    // ---------------------------------------------------------------------------
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! Logging you in...',
        'user' => [
            'id' => $userId,
            'email' => $email,
            'name' => trim($firstName . ' ' . $lastName),
            'role' => 'patient',
        ],
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // SQLSTATE 23505 = unique_violation (PostgreSQL)
    $sqlState = $e->errorInfo[0] ?? $e->getCode();
    if ($sqlState === '23505') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed.',
            'errors'  => ['email' => 'This email address is already registered.'],
        ]);
    } else {
        error_log('OHAQRS register PDOException [' . $sqlState . ']: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'A server error occurred during registration. Please try again later.',
        ]);
    }
    exit;

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OHAQRS register RuntimeException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
