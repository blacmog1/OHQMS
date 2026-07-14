<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

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

$search = trim((string)($_GET['q'] ?? ''));

if ($search === '' || strlen($search) < 2) {
    echo json_encode(['success' => true, 'patients' => []]);
    exit;
}

try {
    $searchTerm = "%{$search}%";
    $stmt = $pdo->prepare("
        SELECT p.patient_id, p.first_name, p.last_name, p.email, p.phone_number,
               u.email AS user_email,
               qt.ticket_code, qt.status AS ticket_status, qt.queue_position,
               d.department_name
        FROM patient p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN queue_ticket qt ON p.patient_id = qt.patient_id AND qt.status IN ('waiting', 'checked_in', 'called', 'in_service')
        LEFT JOIN department d ON qt.department_id = d.department_id
        WHERE UPPER(p.first_name) LIKE UPPER(:search)
           OR UPPER(p.last_name) LIKE UPPER(:search)
           OR UPPER(p.email) LIKE UPPER(:search)
           OR UPPER(p.phone_number) LIKE UPPER(:search)
        ORDER BY qt.status DESC, qt.queue_position ASC
        LIMIT 20
    ");
    $stmt->execute([':search' => $searchTerm]);
    $results = $stmt->fetchAll();

    $patients = array_map(function($r) {
        return [
            'patient_id' => (int)$r['patient_id'],
            'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'email' => $r['email'] ?? $r['user_email'],
            'phone' => $r['phone_number'],
            'ticket_code' => $r['ticket_code'] ?? null,
            'ticket_status' => $r['ticket_status'] ?? null,
            'queue_position' => $r['queue_position'] ?? null,
            'department' => $r['department_name'] ?? null,
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'patients' => $patients,
        'count' => count($patients),
    ]);
} catch (PDOException $e) {
    error_log('Patient search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
