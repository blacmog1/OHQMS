<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="queue-report-' . date('Y-m-d') . '.csv"');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit;
}

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff access required.']);
    exit;
}

try {
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, ['Ticket Code', 'Patient Name', 'Phone', 'Department', 'Status', 'Queue Position', 'Entry Channel', 'Checked In At', 'Doctor', 'Created At']);

    // Fetch all active tickets for today
    $stmt = $pdo->prepare("
        SELECT qt.ticket_code, qt.queue_position, qt.status, qt.entry_channel, qt.checked_in_at, qt.created_at,
               p.first_name, p.last_name, p.phone_number,
               d.department_name,
               u.first_name AS doctor_first, u.last_name AS doctor_last
        FROM queue_ticket qt
        JOIN patient p ON qt.patient_id = p.patient_id
        JOIN department d ON qt.department_id = d.department_id
        LEFT JOIN doctor doc ON qt.doctor_id = doc.doctor_id
        LEFT JOIN users u ON doc.user_id = u.id
        WHERE DATE(qt.created_at) = CURRENT_DATE
        ORDER BY d.department_name, qt.queue_position ASC
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll();

    foreach ($tickets as $ticket) {
        fputcsv($output, [
            $ticket['ticket_code'],
            trim(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')),
            $ticket['phone_number'] ?? '',
            $ticket['department_name'] ?? '',
            $ticket['status'],
            $ticket['queue_position'] ?? '',
            $ticket['entry_channel'] ?? '',
            $ticket['checked_in_at'] ? date('H:i', strtotime($ticket['checked_in_at'])) : '',
            $ticket['doctor_first'] ? trim($ticket['doctor_first'] . ' ' . $ticket['doctor_last']) : '',
            $ticket['created_at'] ? date('Y-m-d H:i', strtotime($ticket['created_at'])) : '',
        ]);
    }

    fclose($output);
    exit;
} catch (PDOException $e) {
    error_log('Export queue report error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Export failed.']);
    exit;
}
