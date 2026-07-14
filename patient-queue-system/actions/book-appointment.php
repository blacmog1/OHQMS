<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Book Appointment / Join Queue
 *
 * Route:  POST /actions/book-appointment.php
 * Access: patient (self), admin/receptionist (walk-in registration)
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

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

$sessionRole   = $_SESSION['role'] ?? '';
$sessionUserId = (int)$_SESSION['user_id'];
$allowedRoles  = ['patient', 'admin', 'receptionist'];

if (!in_array($sessionRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) { $input = $decoded; }
} else {
    $input = $_POST;
}

$departmentId    = $input['department_id'] ?? null;
$entryChannel    = trim((string)($input['entry_channel'] ?? 'online'));
$doctorId        = $input['doctor_id'] ?? null;
$scheduledSlotAt = trim((string)($input['scheduled_slot_at'] ?? ''));
$patientName     = trim((string)($input['patient_name'] ?? ''));
$patientPhone    = trim((string)($input['phone'] ?? ''));

$errors = [];

if ($departmentId === null || $departmentId === '') {
    $errors['department_id'] = 'Department ID is required.';
} elseif (!filter_var($departmentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    $errors['department_id'] = 'Department ID must be a positive integer.';
}

if (!in_array($entryChannel, ['online', 'walk_in'], true)) {
    $errors['entry_channel'] = 'entry_channel must be "online" or "walk_in".';
}

if ($doctorId !== null && $doctorId !== '') {
    if (!filter_var($doctorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $errors['doctor_id'] = 'Doctor ID must be a positive integer.';
    }
}

$slotValue = null;
if ($scheduledSlotAt !== '') {
    $parsedSlot = DateTime::createFromFormat(DateTime::ATOM, $scheduledSlotAt)
               ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $scheduledSlotAt);
    if (!$parsedSlot) {
        $errors['scheduled_slot_at'] = 'scheduled_slot_at must be a valid ISO 8601 datetime.';
    } else {
        $slotValue = $parsedSlot->format('Y-m-d H:i:sP');
    }
}

if ($entryChannel === 'online' && $slotValue === null) {
    $slotValue = (new DateTime())->format('Y-m-d H:i:sP');
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

$departmentId = (int)$departmentId;
$doctorId     = ($doctorId !== null && $doctorId !== '') ? (int)$doctorId : null;

/**
 * Resolve patient_id: self-service for patients, walk-in creation for staff.
 */
function resolvePatientId(PDO $pdo, string $role, int $userId, string $patientName, string $patientPhone): int
{
    if ($role === 'patient') {
        $stmt = $pdo->prepare('SELECT patient_id FROM patient WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('No patient profile found for your account.');
        }
        return (int)$row['patient_id'];
    }

    if ($patientName === '') {
        throw new RuntimeException('patient_name is required for walk-in registration.');
    }

    $parts = preg_split('/\s+/', $patientName, 2);
    $firstName = $parts[0];
    $lastName  = $parts[1] ?? 'Walk-in';

    if ($patientPhone !== '') {
        $find = $pdo->prepare(
            'SELECT patient_id FROM patient WHERE phone_number = :phone ORDER BY patient_id LIMIT 1'
        );
        $find->execute([':phone' => $patientPhone]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return (int)$existing['patient_id'];
        }
    }

    $ins = $pdo->prepare(
        'INSERT INTO patient (first_name, last_name, phone_number, created_at)
         VALUES (:first, :last, :phone, NOW())
         RETURNING patient_id'
    );
    $ins->execute([
        ':first' => $firstName,
        ':last'  => $lastName,
        ':phone' => ($patientPhone !== '') ? $patientPhone : null,
    ]);
    return (int)$ins->fetchColumn();
}

try {
    $patientId = resolvePatientId($pdo, $sessionRole, $sessionUserId, $patientName, $patientPhone);

    $stmtDept = $pdo->prepare(
        'SELECT department_id, department_name FROM department WHERE department_id = :did LIMIT 1'
    );
    $stmtDept->execute([':did' => $departmentId]);
    $dept = $stmtDept->fetch(PDO::FETCH_ASSOC);
    if (!$dept) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Department not found.']);
        exit;
    }

    if ($doctorId !== null) {
        $stmtDoc = $pdo->prepare(
            "SELECT doctor_id FROM doctor
             WHERE doctor_id = :did AND department_id = :dept_id AND status != 'on_break'
             LIMIT 1"
        );
        $stmtDoc->execute([':did' => $doctorId, ':dept_id' => $departmentId]);
        if (!$stmtDoc->fetch()) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Doctor not available in this department.']);
            exit;
        }
    }

    $stmtDup = $pdo->prepare(
        "SELECT ticket_id FROM queue_ticket
         WHERE patient_id = :pid AND department_id = :dept_id
           AND status NOT IN ('completed', 'cancelled', 'no_show')
           AND created_at >= CURRENT_DATE
         LIMIT 1"
    );
    $stmtDup->execute([':pid' => $patientId, ':dept_id' => $departmentId]);
    if ($stmtDup->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Patient already has an active ticket in this department today.']);
        exit;
    }

    $pdo->beginTransaction();

    $book = $pdo->prepare(
        'SELECT * FROM book_queue_ticket(:patient_id, :dept_id, :channel::entry_channel, :slot_at)'
    );
    $book->execute([
        ':patient_id' => $patientId,
        ':dept_id'    => $departmentId,
        ':channel'    => $entryChannel,
        ':slot_at'    => $slotValue,
    ]);
    $ticket = $book->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new RuntimeException('Failed to create queue ticket.');
    }

    $ticketId = (int)$ticket['ticket_id'];

    if ($doctorId !== null) {
        $pdo->prepare('UPDATE queue_ticket SET doctor_id = :doc_id WHERE ticket_id = :tid')
            ->execute([':doc_id' => $doctorId, ':tid' => $ticketId]);
    }

    $pdo->commit();

    $stmtPos = $pdo->prepare(
        "SELECT COUNT(*) FROM queue_ticket
         WHERE department_id = :dept_id
           AND status IN ('waiting', 'checked_in')
           AND effective_queue_at < (
               SELECT effective_queue_at FROM queue_ticket WHERE ticket_id = :tid
           )"
    );
    $stmtPos->execute([':dept_id' => $departmentId, ':tid' => $ticketId]);
    $position = (int)$stmtPos->fetchColumn() + 1;

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Queue ticket created.',
        'ticket'  => [
            'ticket_id'      => $ticketId,
            'ticket_code'    => $ticket['ticket_code'],
            'department'     => $dept['department_name'],
            'status'         => $ticket['status'],
            'queue_position' => $position,
            'entry_channel'  => $entryChannel,
        ],
    ]);
    exit;

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('OHAQRS book-appointment PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
