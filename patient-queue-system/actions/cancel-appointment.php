<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Cancel Appointment
 *
 * Route:  POST /actions/cancel-appointment.php
 * Access: Patient (own tickets only) | Admin | Receptionist
 *
 * Cancels a queue_ticket that is still in a cancellable state
 * (waiting, checked_in). Logs a tracking event.
 *
 * Request JSON:
 *   { "ticket_id": 42 }
 */



// ---------------------------------------------------------------------------
// 1. Bootstrap
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// 2. Method guard
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
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

$allowedRoles = ['patient', 'admin', 'receptionist'];
if (!in_array($sessionRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

$targetStatus = 'cancelled';
if ($sessionRole !== 'patient' && $markAs === 'no_show') {
    $targetStatus = 'no_show';
}

// ---------------------------------------------------------------------------
// 4. Input parsing
// ---------------------------------------------------------------------------
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) { $input = $decoded; }
} else {
    $input = $_POST;
}

$ticketIdParam = $input['ticket_id'] ?? null;
$markAs        = trim((string)($input['mark_as'] ?? 'cancelled'));
$markAs        = trim((string)($input['mark_as'] ?? 'cancelled'));

// ---------------------------------------------------------------------------
// 5. Validation
// ---------------------------------------------------------------------------
if ($ticketIdParam === null || $ticketIdParam === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.',
                      'errors' => ['ticket_id' => 'ticket_id is required.']]);
    exit;
}
if (!filter_var($ticketIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.',
                      'errors' => ['ticket_id' => 'ticket_id must be a positive integer.']]);
    exit;
}
$ticketId = (int)$ticketIdParam;

// ---------------------------------------------------------------------------
// 6. Fetch ticket and enforce ownership for patients
// ---------------------------------------------------------------------------
try {
    $stmtTicket = $pdo->prepare(
        "SELECT qt.ticket_id, qt.patient_id, qt.status, qt.ticket_code,
                qt.department_id, p.user_id AS patient_user_id
         FROM   queue_ticket qt
         JOIN   patient p ON qt.patient_id = p.patient_id
         WHERE  qt.ticket_id = :tid
         LIMIT  1"
    );
    $stmtTicket->execute([':tid' => $ticketId]);
    $ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    // Patients can only cancel their own tickets
    if ($sessionRole === 'patient' && (int)$ticket['patient_user_id'] !== $sessionUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only cancel your own appointments.']);
        exit;
    }

    // Staff may mark no-show; patients can only cancel
    $targetStatus = 'cancelled';
    if ($sessionRole !== 'patient' && $markAs === 'no_show') {
        $targetStatus = 'no_show';
    }

    // Only cancellable when still in queue
    $cancellableStatuses = ['waiting', 'checked_in'];
    if (!in_array($ticket['status'], $cancellableStatuses, true)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "Cannot update a ticket with status '{$ticket['status']}'. Only 'waiting' or 'checked_in' tickets can be changed.",
        ]);
        exit;
    }

    // ---------------------------------------------------------------------------
    // 7. Atomic status update + tracking event
    // ---------------------------------------------------------------------------
    $pdo->beginTransaction();

    $pdo->prepare(
        "UPDATE queue_ticket
         SET    status = :status, updated_at = NOW()
         WHERE  ticket_id = :tid"
    )->execute([':status' => $targetStatus, ':tid' => $ticketId]);

    $pdo->prepare(
        "INSERT INTO real_time_tracking
             (entity_type, entity_id, event_type, event_time, metadata)
         VALUES ('queue_ticket', :eid, 'status_change', NOW(), :meta)"
    )->execute([
        ':eid'  => $ticketId,
        ':meta' => json_encode([
            'from_status'  => $ticket['status'],
            'to_status'    => $targetStatus,
            'ticket_code'  => $ticket['ticket_code'],
            'changed_by'   => $sessionRole,
        ]),
    ]);

    $pdo->commit();

    $message = $targetStatus === 'no_show'
        ? 'Patient marked as no-show.'
        : 'Appointment cancelled successfully.';

    echo json_encode([
        'success' => true,
        'message' => $message,
        'ticket'  => [
            'ticket_id'   => $ticketId,
            'ticket_code' => $ticket['ticket_code'],
            'status'      => $targetStatus,
        ],
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('OHAQRS cancel-appointment PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
