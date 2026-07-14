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

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['receptionist', 'admin', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff access required.']);
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
$departmentId = isset($input['department_id']) ? (int)$input['department_id'] : 0;
$acuityLevel = isset($input['acuity_level']) ? (int)$input['acuity_level'] : 0;
$primarySymptom = trim((string)($input['primary_symptom'] ?? ''));
$checkInLoc = trim((string)($input['check_in_location'] ?? ''));
$lastVitals = $input['last_vitals'] ?? null;

$errors = [];
if ($patientId < 1) $errors['patient_id'] = 'Patient is required.';
if ($departmentId < 1) $errors['department_id'] = 'Department is required.';
if ($acuityLevel < 1 || $acuityLevel > 5) $errors['acuity_level'] = 'Acuity level must be 1-5.';
if ($primarySymptom === '') $errors['primary_symptom'] = 'Primary symptom is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT register_emergency_patient(:patient_id, :department_id, :acuity_level, :symptom, :location, :vitals)");
    $stmt->execute([
        ':patient_id' => $patientId,
        ':department_id' => $departmentId,
        ':acuity_level' => $acuityLevel,
        ':symptom' => $primarySymptom,
        ':location' => $checkInLoc ?: null,
        ':vitals' => $lastVitals ? json_encode($lastVitals) : null,
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => $acuityLevel <= 2 ? 'Emergency registered. Doctors notified immediately.' : 'Emergency patient registered.',
        'triage' => $result,
    ]);
} catch (PDOException $e) {
    error_log('Register emergency patient error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
