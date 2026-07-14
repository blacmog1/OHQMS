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

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

$errors = [];
if ($currentPassword === '') {
    $errors['current_password'] = 'Current password is required.';
}
if (strlen($newPassword) < 8) {
    $errors['new_password'] = 'New password must be at least 8 characters.';
}
if ($newPassword !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $currentHash = $stmt->fetchColumn();

    if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :user_id");
    $stmt->execute([':hash' => $newHash, ':user_id' => $userId]);

    $GLOBALS['logger']->audit('password_change', 'user', $userId, $userId);

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully.',
    ]);
} catch (PDOException $e) {
    error_log('Change password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
