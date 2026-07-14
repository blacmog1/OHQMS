<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Serve Next Patient
 * Uses PostgreSQL get_next_queue_patient() for atomic queue ordering.
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

if (!in_array($sessionRole, ['doctor', 'admin', 'receptionist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Staff access required.']);
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

try {
    $doctorId = null;

    if ($sessionRole === 'doctor') {
        $stmtDoc = $pdo->prepare(
            'SELECT doctor_id FROM doctor WHERE user_id = :uid LIMIT 1'
        );
        $stmtDoc->execute([':uid' => $sessionUserId]);
        $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$docRow) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No doctor profile linked to your account.']);
            exit;
        }
        $doctorId = (int)$docRow['doctor_id'];
    } else {
        $deptParam = $input['department_id'] ?? null;
        if (!$deptParam || !filter_var($deptParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['department_id' => 'department_id is required for admin/receptionist unless doctor_id is provided.'],
            ]);
            exit;
        }

        $doctorParam = $input['doctor_id'] ?? null;
        if ($doctorParam && filter_var($doctorParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $doctorId = (int)$doctorParam;
        } else {
            $avail = $pdo->prepare(
                "SELECT doctor_id FROM doctor
                 WHERE department_id = :dept_id AND status = 'available'
                 ORDER BY doctor_id LIMIT 1"
            );
            $avail->execute([':dept_id' => (int)$deptParam]);
            $availRow = $avail->fetch(PDO::FETCH_ASSOC);
            $doctorId = $availRow ? (int)$availRow['doctor_id'] : null;
        }

        if ($doctorId === null) {
            $pdo->beginTransaction();
            $stmtNext = $pdo->prepare(
                "SELECT ticket_id, ticket_code, patient_id
                 FROM queue_ticket
                 WHERE department_id = :dept_id
                   AND status IN ('waiting', 'checked_in')
                 ORDER BY effective_queue_at ASC, ticket_id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED"
            );
            $stmtNext->execute([':dept_id' => (int)$deptParam]);
            $nextTicket = $stmtNext->fetch(PDO::FETCH_ASSOC);

            if (!$nextTicket) {
                $pdo->rollBack();
                echo json_encode(['success' => true, 'message' => 'Queue is empty.', 'ticket' => null]);
                exit;
            }

            $pdo->prepare(
                "UPDATE queue_ticket SET status = 'called', called_at = NOW(), updated_at = NOW()
                 WHERE ticket_id = :tid"
            )->execute([':tid' => (int)$nextTicket['ticket_id']]);

            $pdo->prepare(
                "INSERT INTO real_time_tracking (entity_type, entity_id, event_type, event_time, metadata)
                 VALUES ('queue_ticket', :eid, 'called', NOW(), :meta)"
            )->execute([
                ':eid'  => (int)$nextTicket['ticket_id'],
                ':meta' => json_encode(['department_id' => (int)$deptParam, 'ticket_code' => $nextTicket['ticket_code']]),
            ]);

            $pdo->commit();

            $stmtPat = $pdo->prepare('SELECT first_name, last_name FROM patient WHERE patient_id = :pid LIMIT 1');
            $stmtPat->execute([':pid' => (int)$nextTicket['patient_id']]);
            $pat = $stmtPat->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Next patient called.',
                'ticket'  => [
                    'ticket_id'    => (int)$nextTicket['ticket_id'],
                    'ticket_code'  => $nextTicket['ticket_code'],
                    'patient_name' => $pat ? $pat['first_name'] . ' ' . $pat['last_name'] : 'Unknown',
                    'status'       => 'called',
                ],
            ]);
            exit;
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM get_next_queue_patient(:doctor_id)');
    $stmt->execute([':doctor_id' => $doctorId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => true, 'message' => 'Queue is empty.', 'ticket' => null]);
        exit;
    }

    $stmtPat = $pdo->prepare('SELECT first_name, last_name FROM patient WHERE patient_id = :pid LIMIT 1');
    $stmtPat->execute([':pid' => (int)$ticket['patient_id']]);
    $pat = $stmtPat->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Next patient called.',
        'ticket'  => [
            'ticket_id'    => (int)$ticket['ticket_id'],
            'ticket_code'  => $ticket['ticket_code'],
            'patient_name' => $pat ? $pat['first_name'] . ' ' . $pat['last_name'] : 'Unknown',
            'status'       => 'called',
        ],
    ]);
    exit;

} catch (PDOException $e) {
    error_log('OHAQRS serve-next-patient PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
