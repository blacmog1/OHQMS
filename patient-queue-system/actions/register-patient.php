<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

$allowedRoles = ['receptionist', 'admin'];
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Receptionist or Admin access required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$firstName = trim((string)($input['first_name'] ?? ''));
$lastName = trim((string)($input['last_name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$phone = trim((string)($input['phone_number'] ?? ''));
$dob = trim((string)($input['date_of_birth'] ?? ''));
$gender = trim((string)($input['gender'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$password = $input['password'] ?? bin2hex(random_bytes(4));

$errors = [];
if ($firstName === '') $errors['first_name'] = 'First name is required.';
if ($lastName === '') $errors['last_name'] = 'Last name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
if ($phone === '') $errors['phone_number'] = 'Phone number is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $pdo->beginTransaction();

    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $options = defined('PASSWORD_ARGON2ID')
        ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
        : ['cost' => 12];
    $passwordHash = password_hash($password, $algo, $options);

    $userStmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, role, created_at, updated_at)
        VALUES (:email, :hash, 'patient', NOW(), NOW())
        RETURNING id
    ");
    $userStmt->execute([
        ':email' => $email,
        ':hash' => $passwordHash,
    ]);
    $userId = (int)$userStmt->fetchColumn();

    $patientStmt = $pdo->prepare("
        INSERT INTO patient (first_name, last_name, email, phone_number, date_of_birth, user_id, created_at)
        VALUES (:first, :last, :email, :phone, :dob, :uid, NOW())
        RETURNING patient_id
    ");
    $patientStmt->execute([
        ':first' => $firstName,
        ':last'  => $lastName,
        ':email' => $email,
        ':phone' => $phone,
        ':dob'   => $dob ?: null,
        ':uid'   => $userId,
    ]);
    $patientId = (int)$patientStmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Patient registered successfully.',
        'user_id' => $userId,
        'patient_id' => $patientId,
        'temporary_password' => $password,
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    if (str_contains($e->getMessage(), 'users_email_key')) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists.']);
    } else {
        error_log('Register patient error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
}
exit;
