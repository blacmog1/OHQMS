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

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$departmentId = isset($input['department_id']) ? (int)$input['department_id'] : 0;
$departmentName = isset($input['department_name']) ? trim((string)$input['department_name']) : null;
$prefixCode = isset($input['prefix_code']) ? strtoupper(trim((string)$input['prefix_code'])) : null;

if ($departmentId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid department ID.']);
    exit;
}

$fields = [];
$params = [':department_id' => $departmentId];

if ($departmentName !== null && $departmentName !== '') {
    $fields[] = 'department_name = :name';
    $params[':name'] = $departmentName;
}
if ($prefixCode !== null && $prefixCode !== '') {
    $fields[] = 'prefix_code = :prefix';
    $params[':prefix'] = $prefixCode;
}

if (empty($fields)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No fields to update.']);
    exit;
}

try {
    $setClause = implode(', ', $fields);
    $stmt = $pdo->prepare("
        UPDATE department 
        SET $setClause
        WHERE department_id = :department_id
        RETURNING department_id, department_name, prefix_code
    ");
    $stmt->execute($params);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dept) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Department updated successfully.',
        'department' => $dept,
    ]);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'department_name') || str_contains($e->getMessage(), 'prefix_code')) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Department name or prefix code already exists.']);
    } else {
        error_log('Update department error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
}
exit;
