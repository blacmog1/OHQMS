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

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
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

$firstName = trim((string)($input['first_name'] ?? ''));
$lastName = trim((string)($input['last_name'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$dob = trim((string)($input['dob'] ?? ''));
$gender = trim((string)($input['gender'] ?? ''));
$address = trim((string)($input['address'] ?? ''));

$errors = [];
if ($firstName === '') $errors['first_name'] = 'First name is required.';
if ($lastName === '') $errors['last_name'] = 'Last name is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE users SET updated_at = NOW() WHERE id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);

    $stmt = $pdo->prepare("
        SELECT patient_id FROM patient WHERE user_id = :user_id LIMIT 1
    ");
    $stmt->execute([':user_id' => $userId]);
    $patientId = $stmt->fetchColumn();

    if ($patientId) {
        $stmt = $pdo->prepare("
            UPDATE patient SET
                first_name = :first_name,
                last_name = :last_name,
                phone_number = :phone,
                date_of_birth = :dob,
                updated_at = NOW()
            WHERE patient_id = :patient_id
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone ?: null,
            ':dob' => $dob ?: null,
            ':patient_id' => (int)$patientId,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO patient (first_name, last_name, email, phone_number, date_of_birth, user_id, created_at)
            SELECT :first_name, :last_name, u.email, :phone, :dob, u.id, NOW()
            FROM users u WHERE u.id = :user_id
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone' => $phone ?: null,
            ':dob' => $dob ?: null,
            ':user_id' => $userId,
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Update profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
