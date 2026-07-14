<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Forgot Password (Temporary Password Reset)
 *
 * Route: POST /actions/forgot-password.php
 * Generates a secure temporary password for an active user account.
 * In production, this would send email — here it returns the temp password
 * for clinic-administrated recovery during deployment/testing.
 */



header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$input = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
$email = trim((string)($input['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit;
}

$algo    = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = defined('PASSWORD_ARGON2ID')
    ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
    : ['cost' => 12];

try {
    $stmt = $pdo->prepare(
        'SELECT id, email FROM users WHERE email = :email AND is_active = TRUE LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always respond success to avoid email enumeration
    if (!$user) {
        echo json_encode([
            'success' => true,
            'message' => 'If that email is registered, a temporary password has been generated.',
        ]);
        exit;
    }

    $tempPassword = bin2hex(random_bytes(4)); // 8-char hex password
    $hash = password_hash($tempPassword, $algo, $options);

    $upd = $pdo->prepare(
        'UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id'
    );
    $upd->execute([':hash' => $hash, ':id' => (int)$user['id']]);

    echo json_encode([
        'success'       => true,
        'message'       => 'Temporary password generated. Use it to sign in and change your password.',
        'temp_password' => $tempPassword,
    ]);
} catch (PDOException $e) {
    error_log('OHAQRS forgot-password PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
exit;
