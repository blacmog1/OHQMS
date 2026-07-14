<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Update Doctor Status
 *
 * Route:  POST /actions/update-doctor-status.php
 * Access: The doctor themselves | Admin
 *
 * Allows a doctor to toggle their own availability status,
 * or an admin to set any doctor's status.
 *
 * Request JSON:
 *   {
 *     "status":    "available",  // required: available | busy | on_break
 *     "doctor_id": 3             // required only for admin callers
 *   }
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

$sessionRole   = $_SESSION['role']    ?? '';
$sessionUserId = (int)$_SESSION['user_id'];

if (!in_array($sessionRole, ['doctor', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden. Doctors and admins only.']);
    exit;
}

// Input
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) { $input = $decoded; }
} else {
    $input = $_POST;
}

$newStatus    = trim((string)($input['status']    ?? ''));
$doctorIdParam = $input['doctor_id'] ?? null;

// Validation
$validStatuses = ['available', 'busy', 'on_break'];
if (!in_array($newStatus, $validStatuses, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false, 'message' => 'Validation failed.',
        'errors'  => ['status' => 'status must be one of: available, busy, on_break.'],
    ]);
    exit;
}

try {
    if ($sessionRole === 'doctor') {
        // Resolve doctor_id from session
        $stmtDoc = $pdo->prepare(
            "SELECT doctor_id FROM doctor WHERE user_id = :uid LIMIT 1"
        );
        $stmtDoc->execute([':uid' => $sessionUserId]);
        $docRow = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$docRow) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No doctor profile linked to your account.']);
            exit;
        }
        $targetDoctorId = (int)$docRow['doctor_id'];

    } else {
        // Admin must supply doctor_id
        if (!$doctorIdParam || !filter_var($doctorIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            http_response_code(422);
            echo json_encode([
                'success' => false, 'message' => 'Validation failed.',
                'errors'  => ['doctor_id' => 'doctor_id is required for admin callers.'],
            ]);
            exit;
        }
        $targetDoctorId = (int)$doctorIdParam;

        // Confirm the doctor exists
        $stmtCheck = $pdo->prepare("SELECT doctor_id FROM doctor WHERE doctor_id = :did LIMIT 1");
        $stmtCheck->execute([':did' => $targetDoctorId]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => "Doctor with ID $targetDoctorId not found."]);
            exit;
        }
    }

    $pdo->prepare(
        "UPDATE doctor SET status = :status WHERE doctor_id = :did"
    )->execute([':status' => $newStatus, ':did' => $targetDoctorId]);

    // Log the status change
    $pdo->prepare(
        "INSERT INTO real_time_tracking
             (entity_type, entity_id, event_type, event_time, doctor_id, metadata)
         VALUES ('doctor', :eid, 'doctor_status_change', NOW(), :doc_id, :meta)"
    )->execute([
        ':eid'    => $targetDoctorId,
        ':doc_id' => $targetDoctorId,
        ':meta'   => json_encode(['new_status' => $newStatus, 'changed_by' => $sessionRole]),
    ]);

    echo json_encode([
        'success'   => true,
        'message'   => "Doctor status updated to '$newStatus'.",
        'doctor_id' => $targetDoctorId,
        'status'    => $newStatus,
    ]);
    exit;

} catch (PDOException $e) {
    error_log('OHAQRS update-doctor-status PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
    exit;
}
