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

$departmentName = trim((string)($input['department_name'] ?? ''));
$prefixCode = strtoupper(trim((string)($input['prefix_code'] ?? '')));

$errors = [];
if ($departmentName === '') $errors['department_name'] = 'Department name is required.';
if ($prefixCode === '') $errors['prefix_code'] = 'Prefix code is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validation failed.', 'errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO department (department_name, prefix_code, created_at)
        VALUES (:name, :prefix, NOW())
        RETURNING department_id, department_name, prefix_code
    ");
    $stmt->execute([
        ':name' => $departmentName,
        ':prefix' => $prefixCode,
    ]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Department created successfully.',
        'department' => $dept,
    ]);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'department_name') || str_contains($e->getMessage(), 'prefix_code')) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Department name or prefix code already exists.']);
    } else {
        error_log('Add department error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
}
exit;
