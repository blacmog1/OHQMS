<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Current Logged-in User Session Details
 *
 * Route:  GET /actions/get-current-user.php
 * Access: Authenticated user
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No active session.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User no longer exists.']);
        exit;
    }

    $name = 'User';
    if ($user['role'] === 'patient') {
        $stmtProfile = $pdo->prepare("SELECT first_name, last_name FROM patient WHERE user_id = :id LIMIT 1");
        $stmtProfile->execute([':id' => $user['id']]);
        $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $name = $profile['first_name'] . ' ' . $profile['last_name'];
        }
    } else if ($user['role'] === 'doctor') {
        $stmtProfile = $pdo->prepare("SELECT first_name, last_name FROM doctor WHERE user_id = :id LIMIT 1");
        $stmtProfile->execute([':id' => $user['id']]);
        $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $name = $profile['first_name'] . ' ' . $profile['last_name'];
        }
    } else if ($user['role'] === 'receptionist' || $user['role'] === 'reception') {
        $name = 'Receptionist';
    } else if ($user['role'] === 'admin') {
        $name = 'Admin User';
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'name'  => $name,
        ]
    ]);

} catch (PDOException $e) {
    error_log('OHAQRS get-current-user PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
exit;
