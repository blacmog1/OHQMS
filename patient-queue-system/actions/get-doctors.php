<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Get Doctors
 *
 * Route:  GET /actions/get-doctors.php?department_id=1
 * Access: Any authenticated user
 *
 * Returns a list of doctors for the chosen department (or all doctors if none specified).
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

try {
    $deptId = $_GET['department_id'] ?? null;
    
    if ($deptId !== null && $deptId !== '') {
        if (!filter_var($deptId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'department_id must be a positive integer.']);
            exit;
        }
        $stmt = $pdo->prepare(
            "SELECT d.doctor_id, d.first_name, d.last_name, d.room_number, d.status
             FROM doctor d
             WHERE d.department_id = :dept_id
             ORDER BY d.first_name, d.last_name"
        );
        $stmt->execute([':dept_id' => (int)$deptId]);
    } else {
        $stmt = $pdo->query(
            "SELECT d.doctor_id, d.first_name, d.last_name, d.room_number, d.status, dept.department_name
             FROM doctor d
             JOIN department dept ON d.department_id = dept.department_id
             ORDER BY d.first_name, d.last_name"
        );
    }
    
    $doctors = array_map(static function (array $row): array {
        return [
            'doctor_id'   => (int)$row['doctor_id'],
            'id'          => (int)$row['doctor_id'],
            'first_name'  => $row['first_name'],
            'last_name'   => $row['last_name'],
            'name'        => 'Dr. ' . $row['first_name'] . ' ' . $row['last_name'],
            'room_number' => $row['room_number'],
            'status'      => $row['status'],
            'department_name' => $row['department_name'] ?? null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode([
        'success' => true,
        'doctors' => $doctors,
    ]);
} catch (PDOException $e) {
    error_log('OHAQRS get-doctors PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
}
exit;
