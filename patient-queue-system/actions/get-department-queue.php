<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Department Queue (For Dashboard and Patient Board)
 *
 * Route:  GET /actions/get-department-queue.php?department_id=1
 * Access: Authenticated user
 *
 * Returns the list of all active/recent tickets for a specific department today.
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

$deptId = $_GET['department_id'] ?? null;
if (!$deptId || !filter_var($deptId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'department_id must be a positive integer.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT 
            qt.ticket_id,
            qt.ticket_code,
            qt.status,
            qt.entry_channel,
            qt.sequence_number,
            qt.scheduled_slot_at,
            qt.check_in_at,
            qt.called_at,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            p.phone_number AS patient_phone,
            dr.first_name AS doctor_first_name,
            dr.last_name AS doctor_last_name
         FROM queue_ticket qt
         JOIN patient p ON qt.patient_id = p.patient_id
         LEFT JOIN doctor dr ON qt.doctor_id = dr.doctor_id
         WHERE qt.department_id = :dept_id
           AND qt.created_at >= CURRENT_DATE
         ORDER BY qt.called_at DESC, qt.effective_queue_at ASC"
    );
    $stmt->execute([':dept_id' => (int)$deptId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'queue'   => $tickets,
    ]);
} catch (PDOException $e) {
    error_log('OHAQRS get-department-queue PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
exit;
