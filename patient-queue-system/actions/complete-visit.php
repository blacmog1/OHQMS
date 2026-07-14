<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Complete Visit
 *
 * Route:  POST /actions/complete-visit.php
 * Access: Authenticated doctor, admin, or receptionist
 *
 * Marks the current patient's visit as completed, records service_end_at,
 * resets the doctor's status to 'available', and logs the event.
 *
 * Request JSON:
 *   {
 *     "ticket_id":       42,              // required
 *     "treatment_notes": "Prescribed..."  // optional — saved to patient_record
 *   }
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

if (!in_array($sessionRole, ['doctor', 'admin', 'receptionist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Staff access required.']);
    exit;
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

$ticketIdParam    = $input['ticket_id']       ?? null;
$treatmentNotes   = trim((string)($input['treatment_notes'] ?? ''));
$symptoms         = trim((string)($input['symptoms'] ?? ''));

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
// 6. Resolve doctor_id from session (doctors only)
// ---------------------------------------------------------------------------
try {
    $doctorId = null;
    if ($sessionRole === 'doctor') {
        $stmtDoc = $pdo->prepare(
            "SELECT doctor_id FROM doctor WHERE user_id = :uid LIMIT 1"
        );
        $stmtDoc->execute([':uid' => $sessionUserId]);
        $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$docRow) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No doctor profile linked to your account.']);
            exit;
        }
        $doctorId = (int)$docRow['doctor_id'];
    }

    // ---------------------------------------------------------------------------
    // 7. Fetch the ticket — must be in_service or called state
    // ---------------------------------------------------------------------------
    $stmtTicket = $pdo->prepare(
        "SELECT ticket_id, patient_id, doctor_id, department_id, status, service_start_at, ticket_code
         FROM queue_ticket WHERE ticket_id = :tid LIMIT 1"
    );
    $stmtTicket->execute([':tid' => $ticketId]);
    $ticket = $stmtTicket->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    // Doctors may only complete tickets assigned to them
    if ($sessionRole === 'doctor' && (int)$ticket['doctor_id'] !== $doctorId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only complete your own patient visits.']);
        exit;
    }

    $completableStatuses = ['called', 'in_service'];
    if (!in_array($ticket['status'], $completableStatuses, true)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "Cannot complete a ticket with status '{$ticket['status']}'. Must be 'called' or 'in_service'.",
        ]);
        exit;
    }

    // ---------------------------------------------------------------------------
    // 8. Atomic transaction: complete ticket + patient_record + tracking + doctor status
    // ---------------------------------------------------------------------------
    $pdo->beginTransaction();

    // If service hasn't started yet, set service_start_at = now
    $serviceStart = $ticket['service_start_at'] ?? null;
    $serviceStartSql = $serviceStart ? ':service_start_at' : 'NOW()';

    $stmtComplete = $pdo->prepare(
        "UPDATE queue_ticket
         SET    status          = 'completed',
                service_start_at = COALESCE(service_start_at, NOW()),
                service_end_at  = NOW(),
                updated_at      = NOW()
         WHERE  ticket_id = :tid"
    );
    $stmtComplete->execute([':tid' => $ticketId]);

    // Insert patient_record if treatment notes are provided or just to log the visit
    $assignedDoctorId = $doctorId ?? (int)($ticket['doctor_id'] ?? 0);
    if ($assignedDoctorId > 0) {
        $stmtRecord = $pdo->prepare(
            "INSERT INTO patient_record
                 (patient_id, doctor_id, queue_ticket_id, symptoms, treatment_notes, visit_date, created_at)
             VALUES
                 (:patient_id, :doctor_id, :ticket_id, :symptoms, :notes, NOW(), NOW())"
        );
        $stmtRecord->execute([
            ':patient_id' => (int)$ticket['patient_id'],
            ':doctor_id'  => $assignedDoctorId,
            ':ticket_id'  => $ticketId,
            ':symptoms'   => ($symptoms !== '') ? $symptoms : null,
            ':notes'      => ($treatmentNotes !== '') ? $treatmentNotes : null,
        ]);
    }

    // Reset doctor status → available
    if ($assignedDoctorId > 0) {
        $pdo->prepare("UPDATE doctor SET status = 'available' WHERE doctor_id = :did")
            ->execute([':did' => $assignedDoctorId]);
    }

    // Write tracking event
    $pdo->prepare(
        "INSERT INTO real_time_tracking
             (entity_type, entity_id, event_type, event_time, doctor_id, metadata)
         VALUES ('queue_ticket', :eid, 'service_end', NOW(), :doc_id, :meta)"
    )->execute([
        ':eid'    => $ticketId,
        ':doc_id' => ($assignedDoctorId > 0) ? $assignedDoctorId : null,
        ':meta'   => json_encode([
            'ticket_code'   => $ticket['ticket_code'],
            'department_id' => $ticket['department_id'],
            'had_notes'     => ($treatmentNotes !== ''),
        ]),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Visit completed. Patient record saved.',
        'ticket'  => [
            'ticket_id'   => $ticketId,
            'ticket_code' => $ticket['ticket_code'],
            'status'      => 'completed',
        ],
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('OHAQRS complete-visit PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
