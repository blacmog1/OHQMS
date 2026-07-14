<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Departments
 *
 * Route:  GET /actions/get-departments.php
 * Access: Any authenticated user (needed by booking forms, doctor onboarding UI)
 *
 * Returns the full list of departments with their IDs and prefix codes.
 */

require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

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
    $stmt = $pdo->query(
        "SELECT department_id, department_name, prefix_code
         FROM department ORDER BY department_name"
    );
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'     => true,
        'departments' => $departments,
    ]);
} catch (PDOException $e) {
    error_log('OHAQRS get-departments PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
exit;
