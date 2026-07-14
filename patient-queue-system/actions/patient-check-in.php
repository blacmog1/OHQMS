<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

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

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) { $input = $decoded; }
} else {
    $input = $_POST;
}

$ticketCode = trim((string)($input['ticket_code'] ?? ''));

if ($ticketCode === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Ticket code is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT qt.ticket_id, qt.patient_id, qt.status, qt.entry_channel
        FROM queue_ticket qt
        WHERE qt.ticket_code = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => strtoupper($ticketCode)]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    $allowedStatuses = ['waiting', 'scheduled'];
    if (!in_array($ticket['status'], $allowedStatuses, true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Cannot check in ticket with status '{$ticket['status']}'.", 'current_status' => $ticket['status']]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE queue_ticket
        SET    status = 'checked_in',
               checked_in_at = NOW(),
               updated_at = NOW()
        WHERE  ticket_id = :tid
    ");
    $stmt->execute([':tid' => (int)$ticket['ticket_id']]);

    $pdo->prepare("
        INSERT INTO real_time_tracking (entity_type, entity_id, event_type, event_time, metadata)
        VALUES ('queue_ticket', :eid, 'check_in', NOW(), :meta)
    ")->execute([
        ':eid' => (int)$ticket['ticket_id'],
        ':meta' => json_encode(['ticket_code' => $ticketCode, 'previous_status' => $ticket['status']]),
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Successfully checked in. Please take a seat.',
        'ticket' => ['ticket_id' => (int)$ticket['ticket_id'], 'ticket_code' => $ticketCode, 'status' => 'checked_in'],
    ]);
} catch (PDOException $e) {
    error_log('Patient check-in error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
