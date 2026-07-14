<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Dashboard Statistics
 *
 * Route:  GET /actions/get-dashboard-stats.php
 * Access: Admin and receptionist only
 *
 * Returns aggregated real-time stats for the admin dashboard panel.
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/dashboard-stats.php';

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

if (!in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Admin access required.']);
    exit;
}

try {
    $stats = getDashboardStats($pdo);
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (PDOException $e) {
    error_log('OHAQRS get-dashboard-stats PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
exit;
