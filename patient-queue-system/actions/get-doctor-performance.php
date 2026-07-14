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

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            d.doctor_id,
            d.first_name,
            d.last_name,
            d.status,
            dep.department_name,
            COUNT(DISTINCT pr.record_id) AS total_patients_seen,
            COUNT(DISTINCT qt.ticket_id) AS total_queue_served,
            AVG(EXTRACT(EPOCH FROM (qt.service_end_at - qt.service_start_at)) / 60) AS avg_consult_minutes,
            COUNT(DISTINCT CASE WHEN qt.status = 'completed' THEN qt.ticket_id END) AS completed_visits,
            COUNT(DISTINCT CASE WHEN qt.status = 'no_show' THEN qt.ticket_id END) AS no_shows,
            COUNT(DISTINCT CASE WHEN qt.status = 'cancelled' THEN qt.ticket_id END) AS cancellations
        FROM doctor d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN department dep ON d.department_id = dep.department_id
        LEFT JOIN patient_record pr ON pr.doctor_id = d.doctor_id
        LEFT JOIN queue_ticket qt ON qt.patient_id = pr.patient_id 
            AND qt.status IN ('completed', 'no_show', 'cancelled')
            AND qt.created_at >= CURRENT_DATE - INTERVAL '30 days'
        GROUP BY d.doctor_id, d.first_name, d.last_name, d.status, dep.department_name
        ORDER BY total_patients_seen DESC
    ");
    $doctors = $stmt->fetchAll();

    $formatted = array_map(function($d) {
        $total = (int)($d['total_queue_served'] ?? 0);
        $completed = (int)($d['completed_visits'] ?? 0);
        $noShows = (int)($d['no_shows'] ?? 0);
        $cancellations = (int)($d['cancellations'] ?? 0);
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        
        return [
            'doctor_id' => (int)$d['doctor_id'],
            'name' => trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')),
            'department' => $d['department_name'] ?? 'General',
            'status' => $d['status'],
            'total_patients_seen' => (int)$d['total_patients_seen'],
            'total_queue_served' => $total,
            'completed_visits' => $completed,
            'no_shows' => $noShows,
            'cancellations' => $cancellations,
            'avg_consult_minutes' => $d['avg_consult_minutes'] ? round((float)$d['avg_consult_minutes'], 1) : null,
            'completion_rate' => $completionRate,
        ];
    }, $doctors);

    echo json_encode([
        'success' => true,
        'doctors' => $formatted,
        'period' => 'Last 30 days',
    ]);
} catch (PDOException $e) {
    error_log('Doctor performance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
