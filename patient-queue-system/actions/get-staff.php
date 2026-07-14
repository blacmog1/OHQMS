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

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT u.id, u.email, u.role, u.created_at,
               d.first_name, d.last_name, d.department_id, d.room_number, d.status AS doctor_status,
               dep.department_name
        FROM users u
        LEFT JOIN doctor d ON u.id = d.user_id
        LEFT JOIN department dep ON d.department_id = dep.department_id
        WHERE u.role IN ('doctor', 'receptionist', 'admin')
        ORDER BY u.role, u.created_at DESC
    ");
    $staff = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'staff' => $staff,
    ]);
} catch (PDOException $e) {
    error_log('Get staff error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
