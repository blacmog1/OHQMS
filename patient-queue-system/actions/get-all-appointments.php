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
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    $departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
    $status = $_GET['status'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where = "1=1";
    $params = [];

    if ($dateFrom) {
        $where .= " AND qt.booked_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo) {
        $where .= " AND qt.booked_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }
    if ($departmentId) {
        $where .= " AND qt.department_id = :department_id";
        $params[':department_id'] = $departmentId;
    }
    if ($doctorId) {
        $where .= " AND qt.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctorId;
    }
    if ($status) {
        $where .= " AND qt.status = :status";
        $params[':status'] = $status;
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM queue_ticket qt
        WHERE $where
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT qt.ticket_id, qt.ticket_code, qt.status, qt.entry_channel,
               qt.booked_at, qt.scheduled_slot_at, qt.check_in_at, qt.called_at,
               qt.service_start_at, qt.service_end_at,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.email AS patient_email, p.phone_number AS patient_phone,
               d.first_name AS doctor_first, d.last_name AS doctor_last,
               dep.department_name,
               u.email AS doctor_email
        FROM queue_ticket qt
        JOIN patient p ON qt.patient_id = p.patient_id
        LEFT JOIN doctor d ON qt.doctor_id = d.doctor_id
        LEFT JOIN department dep ON qt.department_id = dep.department_id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE $where
        ORDER BY qt.booked_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
} catch (PDOException $e) {
    error_log('Get all appointments error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
exit;
