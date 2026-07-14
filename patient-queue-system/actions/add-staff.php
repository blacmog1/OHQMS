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

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) { $input = $decoded; }
} else {
    $input = $_POST;
}

$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');
$role = trim((string)($input['role'] ?? ''));
$firstName = trim((string)($input['first_name'] ?? ''));
$lastName = trim((string)($input['last_name'] ?? ''));
$departmentId = isset($input['department_id']) ? (int)$input['department_id'] : null;
$roomNumber = trim((string)($input['room_number'] ?? ''));

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Valid email is required.';
}
if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}
if (!in_array($role, ['doctor', 'receptionist'], true)) {
    $errors['role'] = 'Role must be doctor or receptionist.';
}
if ($firstName === '') {
    $errors['first_name'] = 'First name is required.';
}
if ($lastName === '') {
    $errors['last_name'] = 'Last name is required.';
}
if ($role === 'doctor' && (!$departmentId || $departmentId < 1)) {
    $errors['department_id'] = 'Department is required for doctors.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $pdo->beginTransaction();

    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, role, created_at, updated_at)
        VALUES (:email, :password_hash, :role, NOW(), NOW())
        RETURNING id
    ");
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':role' => $role,
    ]);
    $userId = (int)$stmt->fetchColumn();

    if ($role === 'doctor') {
        $stmt = $pdo->prepare("
            INSERT INTO doctor (first_name, last_name, department_id, user_id, room_number, status, created_at)
            VALUES (:first_name, :last_name, :department_id, :user_id, :room_number, 'available', NOW())
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':department_id' => $departmentId,
            ':user_id' => $userId,
            ':room_number' => $roomNumber ?: null,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => ucfirst($role) . ' added successfully.',
        'user_id' => $userId,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Add staff error: ' . $e->getMessage());
    if (strpos($e->getMessage(), 'users_email_key') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
}
