<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use GET.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';

    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.role,
               p.first_name, p.last_name, p.phone_number, p.date_of_birth
        FROM users u
        LEFT JOIN patient p ON u.id = p.user_id
        WHERE u.id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'phone' => $user['phone_number'] ?? '',
            'dob' => $user['date_of_birth'] ? date('Y-m-d', strtotime($user['date_of_birth'])) : '',
        ],
    ]);
} catch (PDOException $e) {
    error_log('Get profile error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
