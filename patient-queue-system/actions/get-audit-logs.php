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
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $action = $_GET['action'] ?? null;
    $entityType = $_GET['entity_type'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = "1=1";
    $params = [];

    if ($userId) {
        $where .= " AND al.user_id = :user_id";
        $params[':user_id'] = $userId;
    }
    if ($action) {
        $where .= " AND al.action = :action";
        $params[':action'] = $action;
    }
    if ($entityType) {
        $where .= " AND al.entity_type = :entity_type";
        $params[':entity_type'] = $entityType;
    }
    if ($dateFrom) {
        $where .= " AND al.created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $where .= " AND al.created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log al WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT al.log_id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.created_at,
               u.email AS user_email, u.role AS user_role
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE $where
        ORDER BY al.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
} catch (PDOException $e) {
    error_log('Get audit logs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
exit;
