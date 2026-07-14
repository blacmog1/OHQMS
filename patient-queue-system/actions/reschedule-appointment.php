<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../includes/security-logger.php';
require_once __DIR__ . '/../config/db.php';

/**
 * OHAQRS - Reschedule Appointment
 *
 * Route:  POST /actions/reschedule-appointment.php
 * Access: patient (own), admin, receptionist
 * Allows changing appointment time or canceling and rebooking
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$sessionRole = $_SESSION['role'] ?? '';
$sessionUserId = (int)$_SESSION['user_id'];

if (!in_array($sessionRole, ['patient', 'admin', 'receptionist'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
} else {
    $input = $_POST;
}

$ticketId = (int)($input['ticket_id'] ?? 0);
$newScheduledSlot = trim((string)($input['new_scheduled_slot'] ?? ''));
$reason = trim((string)($input['reason'] ?? ''));

$errors = [];

if (!$ticketId) {
    $errors['ticket_id'] = 'ticket_id is required.';
}

if (!$newScheduledSlot) {
    $errors['new_scheduled_slot'] = 'new_scheduled_slot is required.';
} else {
    $parsed = DateTime::createFromFormat(DateTime::ATOM, $newScheduledSlot) 
           ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $newScheduledSlot);
    if (!$parsed) {
        $errors['new_scheduled_slot'] = 'new_scheduled_slot must be a valid ISO 8601 datetime.';
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch current appointment
    $stmt = $pdo->prepare(
        'SELECT qt.ticket_id, qt.patient_id, qt.doctor_id, qt.department_id, qt.status, 
                qt.ticket_code, p.first_name, p.last_name, p.email
         FROM queue_ticket qt
         JOIN patient p ON qt.patient_id = p.patient_id
         WHERE qt.ticket_id = :ticket_id'
    );
    $stmt->execute([':ticket_id' => $ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found.']);
        exit;
    }

    // Verify access
    if ($sessionRole === 'patient') {
        $stmt = $pdo->prepare('SELECT patient_id FROM patient WHERE user_id = :uid');
        $stmt->execute([':uid' => $sessionUserId]);
        $patRow = $stmt->fetch();
        if (!$patRow || $patRow['patient_id'] != $ticket['patient_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot reschedule other patients\' appointments.']);
            exit;
        }
    }

    // Check if appointment can be rescheduled
    $reschedulableStatuses = ['waiting', 'checked_in'];
    if (!in_array($ticket['status'], $reschedulableStatuses)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot reschedule appointments with status: ' . $ticket['status'],
        ]);
        exit;
    }

    // Update appointment with new time
    $newSlotFormatted = $parsed->format('Y-m-d H:i:sP');
    
    $updateStmt = $pdo->prepare(
        'UPDATE queue_ticket 
         SET scheduled_slot_at = :new_slot,
             effective_queue_at = :new_slot,
             status = :status,
             updated_at = NOW()
         WHERE ticket_id = :ticket_id'
    );

    $updateStmt->execute([
        ':new_slot' => $newSlotFormatted,
        ':status' => 'waiting',
        ':ticket_id' => $ticketId,
    ]);

    // Log the rescheduling action
    $GLOBALS['logger']->audit(
        'reschedule_appointment',
        'queue_ticket',
        $ticketId,
        $sessionUserId,
        [
            'old_time' => $ticket['scheduled_slot_at'] ?? null,
            'new_time' => $newSlotFormatted,
            'reason' => $reason,
        ]
    );

    $pdo->commit();

    // Send notification email (if enabled)
    try {
        require_once __DIR__ . '/../includes/email-service.php';
        $emailService = new EmailNotificationService();
        
        $doctor = $pdo->prepare('SELECT first_name, last_name, email FROM doctor WHERE doctor_id = :did')
            ->execute([':did' => $ticket['doctor_id']])
            ->fetch() ?? ['first_name' => 'Doctor', 'last_name' => ''];

        $dept = $pdo->prepare('SELECT department_name FROM department WHERE department_id = :did')
            ->execute([':did' => $ticket['department_id']])
            ->fetch() ?? ['department_name' => 'Department'];

        $emailService->sendAppointmentConfirmation(
            array_merge($ticket, ['scheduled_slot_at' => $newSlotFormatted]),
            ['first_name' => $ticket['first_name'], 'last_name' => $ticket['last_name'], 'email' => $ticket['email']],
            $doctor,
            $dept
        );
    } catch (Exception $e) {
        error_log('Failed to send rescheduling email: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment rescheduled successfully.',
        'new_slot' => $newSlotFormatted,
        'ticket_code' => $ticket['ticket_code'],
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Reschedule appointment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
