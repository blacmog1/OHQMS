<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Queue Status (Patient Polling Endpoint)
 *
 * Route:  GET /actions/get-queue-status.php?ticket_id=42
 *    OR   GET /actions/get-queue-status.php   (uses session patient's active ticket)
 * Access: Authenticated (patient sees own ticket; doctor/admin can query any)
 *
 * Returns real-time position, status, estimated wait, and doctor assignment.
 */



// ---------------------------------------------------------------------------
// 1. Bootstrap
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// 2. Method guard — GET only
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use GET.']);
    exit;
}

// ---------------------------------------------------------------------------
// 3. Auth guard
// ---------------------------------------------------------------------------
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$sessionRole   = $_SESSION['role']    ?? '';
$sessionUserId = (int)$_SESSION['user_id'];

// ---------------------------------------------------------------------------
// 4. Resolve which ticket to look up
// ---------------------------------------------------------------------------
try {
    $ticketIdParam = $_GET['ticket_id'] ?? null;

    if ($ticketIdParam !== null) {
        // Explicit ticket_id supplied
        if (!filter_var($ticketIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ticket_id must be a positive integer.']);
            exit;
        }
        $ticketId = (int)$ticketIdParam;

        // Patients may only query their own tickets
        if ($sessionRole === 'patient') {
            $stmtOwner = $pdo->prepare(
                "SELECT qt.ticket_id FROM queue_ticket qt
                 JOIN patient p ON qt.patient_id = p.patient_id
                 WHERE qt.ticket_id = :tid AND p.user_id = :uid LIMIT 1"
            );
            $stmtOwner->execute([':tid' => $ticketId, ':uid' => $sessionUserId]);
            if (!$stmtOwner->fetch()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied.']);
                exit;
            }
        }

    } else {
        // No ticket_id — find the patient's most recent active ticket today
        if ($sessionRole !== 'patient') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ticket_id parameter is required for non-patient roles.']);
            exit;
        }

        $stmtLatest = $pdo->prepare(
            "SELECT qt.ticket_id FROM queue_ticket qt
             JOIN patient p ON qt.patient_id = p.patient_id
             WHERE p.user_id = :uid
               AND qt.status NOT IN ('completed', 'cancelled', 'no_show')
               AND qt.created_at >= CURRENT_DATE
             ORDER BY qt.created_at DESC
             LIMIT 1"
        );
        $stmtLatest->execute([':uid' => $sessionUserId]);
        $latestRow = $stmtLatest->fetch(PDO::FETCH_ASSOC);

        if (!$latestRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No active ticket found for today.']);
            exit;
        }
        $ticketId = (int)$latestRow['ticket_id'];
    }

    // ---------------------------------------------------------------------------
    // 5. Fetch full ticket details with joins
    // ---------------------------------------------------------------------------
    $stmtTicket = $pdo->prepare(
        "SELECT
             qt.ticket_id,
             qt.ticket_code,
             qt.department_id,
             qt.status,
             qt.entry_channel,
             qt.sequence_number,
             qt.booked_at,
             qt.scheduled_slot_at,
             qt.check_in_at,
             qt.called_at,
             qt.service_start_at,
             qt.service_end_at,
             qt.effective_queue_at,
             d.department_name,
             d.prefix_code,
             p.first_name  AS patient_first_name,
             p.last_name   AS patient_last_name,
             dr.doctor_id,
             dr.first_name AS doctor_first_name,
             dr.last_name  AS doctor_last_name,
             dr.room_number
         FROM  queue_ticket qt
         JOIN  department   d  ON qt.department_id = d.department_id
         JOIN  patient      p  ON qt.patient_id    = p.patient_id
         LEFT JOIN doctor   dr ON qt.doctor_id     = dr.doctor_id
         WHERE qt.ticket_id = :tid"
    );
    $stmtTicket->execute([':tid' => $ticketId]);
    $ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    // ---------------------------------------------------------------------------
    // 6. Calculate queue position (only meaningful when status = waiting/checked_in)
    // ---------------------------------------------------------------------------
    $position = null;
    $activeStatuses = ['waiting', 'checked_in'];
    if (in_array($ticket['status'], $activeStatuses, true)) {
        $stmtPos = $pdo->prepare(
            "SELECT COUNT(*) FROM queue_ticket
             WHERE department_id      = (SELECT department_id FROM queue_ticket WHERE ticket_id = :tid)
               AND status             IN ('waiting', 'checked_in')
               AND effective_queue_at < (SELECT effective_queue_at FROM queue_ticket WHERE ticket_id = :tid2)"
        );
        $stmtPos->execute([':tid' => $ticketId, ':tid2' => $ticketId]);
        $position = (int)$stmtPos->fetchColumn() + 1;
    }

    // ---------------------------------------------------------------------------
    // 7. Estimated wait (avg service time × position ahead)
    // ---------------------------------------------------------------------------
    $estimatedWaitMinutes = null;
    if ($position !== null && $position > 1) {
        $stmtAvg = $pdo->prepare(
            "SELECT COALESCE(
                 ROUND(AVG(EXTRACT(EPOCH FROM (service_end_at - service_start_at)) / 60)),
                 15
             ) AS avg_mins
             FROM queue_ticket
             WHERE department_id = (SELECT department_id FROM queue_ticket WHERE ticket_id = :tid)
               AND status        = 'completed'
               AND service_end_at IS NOT NULL
               AND service_start_at IS NOT NULL
               AND created_at   >= NOW() - INTERVAL '7 days'"
        );
        $stmtAvg->execute([':tid' => $ticketId]);
        $avgMins = (int)($stmtAvg->fetchColumn() ?: 15);
        $estimatedWaitMinutes = ($position - 1) * $avgMins;
    } elseif ($position === 1) {
        $estimatedWaitMinutes = 0;
    }

    // ---------------------------------------------------------------------------
    // 8. Build response
    // ---------------------------------------------------------------------------
    $doctorInfo = null;
    if ($ticket['doctor_id']) {
        $doctorInfo = [
            'doctor_id'   => (int)$ticket['doctor_id'],
            'name'        => 'Dr. ' . $ticket['doctor_first_name'] . ' ' . $ticket['doctor_last_name'],
            'room_number' => $ticket['room_number'],
        ];
    }

    echo json_encode([
        'success' => true,
        'ticket'  => [
            'ticket_id'              => (int)$ticket['ticket_id'],
            'ticket_code'            => $ticket['ticket_code'],
            'status'                 => $ticket['status'],
            'entry_channel'          => $ticket['entry_channel'],
            'department'             => $ticket['department_name'],
            'queue_position'         => $position,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'patient_name'           => $ticket['patient_first_name'] . ' ' . $ticket['patient_last_name'],
            'doctor'                 => $doctorInfo,
            'booked_at'              => $ticket['booked_at'],
            'scheduled_slot_at'      => $ticket['scheduled_slot_at'],
            'called_at'              => $ticket['called_at'],
            'service_start_at'       => $ticket['service_start_at'],
            'service_end_at'         => $ticket['service_end_at'],
        ],
    ]);
    exit;

} catch (PDOException $e) {
    error_log('OHAQRS get-queue-status PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
