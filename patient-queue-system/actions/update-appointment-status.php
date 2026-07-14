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

$allowedRoles = ['receptionist', 'admin', 'doctor'];
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff access required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
$status = strtolower(trim((string)($input['status'] ?? '')));

$allowedStatuses = ['waiting', 'checked_in', 'called', 'in_service', 'completed', 'cancelled', 'no_show'];
if ($ticketId < 1 || !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID or status.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $checkStmt = $pdo->prepare("
        SELECT ticket_id, status FROM queue_ticket WHERE ticket_id = :ticket_id FOR UPDATE
    ");
    $checkStmt->execute([':ticket_id' => $ticketId]);
    $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
        exit;
    }

    $updateFields = ['status = :status', 'updated_at = NOW()'];
    $params = [':ticket_id' => $ticketId, ':status' => $status];

    if ($status === 'checked_in' && empty($ticket['check_in_at'])) {
        $updateFields[] = 'check_in_at = NOW()';
    }
    if ($status === 'called' && empty($ticket['called_at'])) {
        $updateFields[] = 'called_at = NOW()';
    }
    if ($status === 'in_service' && empty($ticket['service_start_at'])) {
        $updateFields[] = 'service_start_at = NOW()';
    }
    if ($status === 'completed' && empty($ticket['service_end_at'])) {
        $updateFields[] = 'service_end_at = NOW()';
    }

    $updateStmt = $pdo->prepare("
        UPDATE queue_ticket 
        SET " . implode(', ', $updateFields) . "
        WHERE ticket_id = :ticket_id
    ");
    $updateStmt->execute($params);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ticket status updated successfully.',
        'ticket_id' => $ticketId,
        'status' => $status,
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Update appointment status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
exit;
