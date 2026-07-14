<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../config/db.php';

/**
 * OHAQRS - Doctor Schedule Management
 *
 * Route:  POST /actions/manage-doctor-schedule.php
 * Access: doctor (own schedule), admin (any doctor)
 * Methods: GET (view), POST (create), PUT (update), DELETE (remove)
 */



require_once __DIR__ . '/../includes/session-config.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$sessionRole = $_SESSION['role'] ?? '';
$sessionUserId = (int)$_SESSION['user_id'];

// Only doctors and admins can manage schedules
if (!in_array($sessionRole, ['doctor', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        handleGetSchedule($pdo, $sessionRole, $sessionUserId);
    } elseif ($method === 'POST') {
        handleCreateSchedule($pdo, $sessionRole, $sessionUserId);
    } elseif ($method === 'PUT') {
        handleUpdateSchedule($pdo, $sessionRole, $sessionUserId);
    } elseif ($method === 'DELETE') {
        handleDeleteSchedule($pdo, $sessionRole, $sessionUserId);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    }

} catch (Exception $e) {
    error_log('Doctor schedule error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}

function handleGetSchedule($pdo, $sessionRole, $sessionUserId) {
    $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : null;

    // Doctors can only view their own schedule
    if ($sessionRole === 'doctor') {
        $stmt = $pdo->prepare('SELECT doctor_id FROM doctor WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $sessionUserId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'No doctor profile found.']);
            return;
        }
        $doctorId = $doc['doctor_id'];
    }

    if (!$doctorId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'doctor_id is required.']);
        return;
    }

    $query = 'SELECT * FROM doctor_schedule WHERE doctor_id = :doctor_id';
    $params = [':doctor_id' => $doctorId];

    if ($date) {
        $query .= ' AND schedule_date = :date';
        $params[':date'] = $date;
    }

    $query .= ' ORDER BY schedule_date, start_time';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'schedules' => $schedules ?? [],
    ]);
}

function handleCreateSchedule($pdo, $sessionRole, $sessionUserId) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $doctorId = (int)($input['doctor_id'] ?? 0);
    $scheduleDate = trim((string)($input['schedule_date'] ?? ''));
    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));
    $maxPatients = (int)($input['max_patients'] ?? 10);
    $isAvailable = (bool)($input['is_available'] ?? true);

    // Validate doctor access
    if ($sessionRole === 'doctor') {
        $stmt = $pdo->prepare('SELECT doctor_id FROM doctor WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $sessionUserId]);
        $doc = $stmt->fetch();
        if (!$doc || $doc['doctor_id'] != $doctorId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot manage other doctors\' schedules.']);
            return;
        }
    }

    $errors = [];

    if (!$scheduleDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate)) {
        $errors['schedule_date'] = 'Valid schedule_date required (YYYY-MM-DD).';
    }

    if (!$startTime || !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
        $errors['start_time'] = 'Valid start_time required (HH:MM).';
    }

    if (!$endTime || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
        $errors['end_time'] = 'Valid end_time required (HH:MM).';
    }

    if ($maxPatients <= 0) {
        $errors['max_patients'] = 'max_patients must be > 0.';
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO doctor_schedule (doctor_id, schedule_date, start_time, end_time, max_patients, is_available, created_at)
         VALUES (:doctor_id, :date, :start, :end, :max, :avail, NOW())'
    );

    $stmt->execute([
        ':doctor_id' => $doctorId,
        ':date' => $scheduleDate,
        ':start' => $startTime,
        ':end' => $endTime,
        ':max' => $maxPatients,
        ':avail' => $isAvailable ? 1 : 0,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Schedule created successfully.',
        'schedule_id' => $pdo->lastInsertId(),
    ]);
}

function handleUpdateSchedule($pdo, $sessionRole, $sessionUserId) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $scheduleId = (int)($input['schedule_id'] ?? 0);
    $isAvailable = isset($input['is_available']) ? (bool)$input['is_available'] : null;
    $maxPatients = isset($input['max_patients']) ? (int)$input['max_patients'] : null;

    if (!$scheduleId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'schedule_id is required.']);
        return;
    }

    // Verify doctor owns this schedule
    $stmt = $pdo->prepare(
        'SELECT ds.doctor_id FROM doctor_schedule ds
         JOIN doctor d ON ds.doctor_id = d.doctor_id
         WHERE ds.schedule_id = :sid'
    );
    $stmt->execute([':sid' => $scheduleId]);
    $sched = $stmt->fetch();

    if (!$sched) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule not found.']);
        return;
    }

    if ($sessionRole === 'doctor') {
        $stmt = $pdo->prepare('SELECT doctor_id FROM doctor WHERE user_id = :uid');
        $stmt->execute([':uid' => $sessionUserId]);
        $doc = $stmt->fetch();
        if (!$doc || $doc['doctor_id'] != $sched['doctor_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot modify other doctors\' schedules.']);
            return;
        }
    }

    $updates = [];
    $params = [':sid' => $scheduleId];

    if ($isAvailable !== null) {
        $updates[] = 'is_available = :avail';
        $params[':avail'] = $isAvailable ? 1 : 0;
    }

    if ($maxPatients !== null) {
        if ($maxPatients <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'max_patients must be > 0.']);
            return;
        }
        $updates[] = 'max_patients = :max';
        $params[':max'] = $maxPatients;
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update.']);
        return;
    }

    $updates[] = 'updated_at = NOW()';
    $query = 'UPDATE doctor_schedule SET ' . implode(', ', $updates) . ' WHERE schedule_id = :sid';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully.']);
}

function handleDeleteSchedule($pdo, $sessionRole, $sessionUserId) {
    $scheduleId = (int)($_GET['schedule_id'] ?? 0);

    if (!$scheduleId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'schedule_id is required.']);
        return;
    }

    // Verify doctor owns this schedule
    $stmt = $pdo->prepare(
        'SELECT ds.doctor_id FROM doctor_schedule ds
         WHERE ds.schedule_id = :sid'
    );
    $stmt->execute([':sid' => $scheduleId]);
    $sched = $stmt->fetch();

    if (!$sched) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Schedule not found.']);
        return;
    }

    if ($sessionRole === 'doctor') {
        $stmt = $pdo->prepare('SELECT doctor_id FROM doctor WHERE user_id = :uid');
        $stmt->execute([':uid' => $sessionUserId]);
        $doc = $stmt->fetch();
        if (!$doc || $doc['doctor_id'] != $sched['doctor_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot delete other doctors\' schedules.']);
            return;
        }
    }

    $stmt = $pdo->prepare('DELETE FROM doctor_schedule WHERE schedule_id = :sid');
    $stmt->execute([':sid' => $scheduleId]);

    echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully.']);
}
