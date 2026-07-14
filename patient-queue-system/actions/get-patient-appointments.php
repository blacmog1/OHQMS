<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Patient Appointments / Tickets
 *
 * Route:  GET /actions/get-patient-appointments.php
 * Access: Authenticated patient
 *
 * Returns a list of all queue tickets/appointments for the logged-in patient.
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

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

if (($_SESSION['role'] ?? '') !== 'patient') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Patient access only.']);
    exit;
}

try {
    $stmtPat = $pdo->prepare("SELECT patient_id FROM patient WHERE user_id = :uid LIMIT 1");
    $stmtPat->execute([':uid' => (int)$_SESSION['user_id']]);
    $patient = $stmtPat->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient profile not found.']);
        exit;
    }
    
    $patientId = (int)$patient['patient_id'];

    $stmt = $pdo->prepare(
        "SELECT 
            qt.ticket_id,
            qt.ticket_code,
            qt.status,
            qt.entry_channel,
            qt.sequence_number,
            qt.scheduled_slot_at,
            qt.booked_at,
            d.department_name,
            dr.first_name AS doctor_first_name,
            dr.last_name AS doctor_last_name,
            dr.room_number
         FROM queue_ticket qt
         JOIN department d ON qt.department_id = d.department_id
         LEFT JOIN doctor dr ON qt.doctor_id = dr.doctor_id
         WHERE qt.patient_id = :pid
         ORDER BY qt.scheduled_slot_at DESC, qt.created_at DESC"
    );
    $stmt->execute([':pid' => $patientId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'appointments' => $tickets,
    ]);
} catch (PDOException $e) {
    error_log('OHAQRS get-patient-appointments PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}
exit;
