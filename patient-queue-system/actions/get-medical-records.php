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

try {
    $userId = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? '';
    $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

    if ($role === 'patient') {
        $stmt = $pdo->prepare("SELECT patient_id FROM patient WHERE user_id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $patientId = (int)$stmt->fetchColumn();
    }

    if (!$patientId || $patientId < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Patient ID required.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT pr.record_id, pr.visit_date, pr.symptoms, pr.treatment_notes,
               d.first_name AS doctor_first_name, d.last_name AS doctor_last_name,
               dep.department_name,
               qt.ticket_code, qt.status AS ticket_status
        FROM patient_record pr
        JOIN doctor d ON pr.doctor_id = d.doctor_id
        LEFT JOIN department dep ON d.department_id = dep.department_id
        LEFT JOIN queue_ticket qt ON pr.queue_ticket_id = qt.ticket_id
        WHERE pr.patient_id = :patient_id
        ORDER BY pr.visit_date DESC
        LIMIT 50
    ");
    $stmt->execute([':patient_id' => $patientId]);
    $records = $stmt->fetchAll();

    $formatted = array_map(function($r) {
        return [
            'record_id' => (int)$r['record_id'],
            'visit_date' => $r['visit_date'],
            'symptoms' => $r['symptoms'],
            'treatment_notes' => $r['treatment_notes'],
            'doctor_name' => trim(($r['doctor_first_name'] ?? '') . ' ' . ($r['doctor_last_name'] ?? '')),
            'department' => $r['department_name'] ?? 'General',
            'ticket_code' => $r['ticket_code'] ?? null,
            'ticket_status' => $r['ticket_status'] ?? null,
        ];
    }, $records);

    echo json_encode([
        'success' => true,
        'records' => $formatted,
        'count' => count($formatted),
    ]);
} catch (PDOException $e) {
    error_log('Get medical records error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
