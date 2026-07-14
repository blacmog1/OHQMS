<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Active Queue Tickets
 *
 * Route: GET /actions/get-active-tickets.php
 * Query: department_id, doctor_id, status (comma-separated)
 * Access: Authenticated staff and patients (patients see own tickets only)
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

$sessionRole   = $_SESSION['role'] ?? '';
$sessionUserId = (int)$_SESSION['user_id'];

try {
    $departmentId = isset($_GET['department_id']) && $_GET['department_id'] !== ''
        ? (int)$_GET['department_id'] : null;
    $doctorIdFilter = isset($_GET['doctor_id']) && $_GET['doctor_id'] !== ''
        ? (int)$_GET['doctor_id'] : null;
    $statusFilter = trim((string)($_GET['status'] ?? ''));

    $where = ['qt.created_at >= CURRENT_DATE'];
    $params = [];

    if ($sessionRole === 'patient') {
        $where[] = 'p.user_id = :session_user_id';
        $params[':session_user_id'] = $sessionUserId;
    } elseif ($sessionRole === 'doctor') {
        $stmtDoc = $pdo->prepare(
            'SELECT doctor_id, department_id FROM doctor WHERE user_id = :uid LIMIT 1'
        );
        $stmtDoc->execute([':uid' => $sessionUserId]);
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No doctor profile linked to your account.']);
            exit;
        }
        $where[] = 'qt.department_id = :doctor_dept_id';
        $params[':doctor_dept_id'] = (int)$doc['department_id'];
    }

    if ($departmentId !== null) {
        $where[] = 'qt.department_id = :dept_id';
        $params[':dept_id'] = $departmentId;
    }

    if ($doctorIdFilter !== null) {
        $where[] = 'qt.doctor_id = :doctor_id';
        $params[':doctor_id'] = $doctorIdFilter;
    }

    if ($statusFilter !== '') {
        $statuses = array_values(array_filter(array_map('trim', explode(',', $statusFilter))));
        if (!empty($statuses)) {
            $placeholders = [];
            foreach ($statuses as $i => $st) {
                $key = ':status_' . $i;
                $placeholders[] = $key;
                $params[$key] = $st;
            }
            $where[] = 'qt.status IN (' . implode(', ', $placeholders) . ')';
        }
    } else {
        $where[] = "qt.status NOT IN ('cancelled', 'no_show')";
    }

    $sql = '
        SELECT
            qt.ticket_id,
            qt.ticket_code,
            qt.status,
            qt.entry_channel,
            qt.sequence_number,
            qt.scheduled_slot_at,
            qt.booked_at,
            qt.effective_queue_at,
            qt.doctor_id,
            p.patient_id,
            p.first_name,
            p.last_name,
            p.phone_number,
            d.department_id,
            d.department_name,
            ROW_NUMBER() OVER (
                PARTITION BY qt.department_id
                ORDER BY qt.effective_queue_at ASC, qt.ticket_id ASC
            ) AS queue_position
        FROM queue_ticket qt
        JOIN patient p ON qt.patient_id = p.patient_id
        JOIN department d ON qt.department_id = d.department_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY qt.department_id, qt.effective_queue_at ASC, qt.ticket_id ASC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tickets = array_map(static function (array $row): array {
        return [
            'id'            => (int)$row['ticket_id'],
            'ticketId'      => (int)$row['ticket_id'],
            'ticketCode'    => $row['ticket_code'],
            'patientId'     => (int)$row['patient_id'],
            'patientName'   => trim($row['first_name'] . ' ' . $row['last_name']),
            'phone'         => $row['phone_number'],
            'department'    => $row['department_name'],
            'department_id' => (int)$row['department_id'],
            'status'        => $row['status'],
            'entryChannel'  => $row['entry_channel'],
            'entry_channel' => $row['entry_channel'],
            'queuePosition' => (int)$row['queue_position'],
            'queueNumber'   => (int)$row['sequence_number'],
            'scheduledAt'   => $row['scheduled_slot_at'],
            'scheduled_slot_at' => $row['scheduled_slot_at'],
            'bookedAt'      => $row['booked_at'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'tickets' => $tickets]);
} catch (PDOException $e) {
    error_log('OHAQRS get-active-tickets PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
exit;
