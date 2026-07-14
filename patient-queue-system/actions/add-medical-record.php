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

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Doctor access required.']);
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

$patientId = isset($input['patient_id']) ? (int)$input['patient_id'] : 0;
$queueTicketId = isset($input['queue_ticket_id']) ? (int)$input['queue_ticket_id'] : null;
$symptoms = trim((string)($input['symptoms'] ?? ''));
$treatmentNotes = trim((string)($input['treatment_notes'] ?? ''));

$errors = [];
if ($patientId < 1) $errors['patient_id'] = 'Patient ID is required.';
if ($symptoms === '') $errors['symptoms'] = 'Symptoms are required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT doctor_id FROM doctor WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $doctorId = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO patient_record (patient_id, doctor_id, queue_ticket_id, symptoms, treatment_notes, visit_date, created_at)
        VALUES (:patient_id, :doctor_id, :ticket_id, :symptoms, :notes, NOW(), NOW())
        RETURNING record_id
    ");
    $stmt->execute([
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':ticket_id' => $queueTicketId,
        ':symptoms' => $symptoms,
        ':notes' => $treatmentNotes ?: null,
    ]);
    $recordId = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Medical record saved.',
        'record_id' => $recordId,
    ]);
} catch (PDOException $e) {
    error_log('Add medical record error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
