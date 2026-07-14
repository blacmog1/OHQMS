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

if ($departmentId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid department ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM doctor WHERE department_id = :dept_id
        UNION ALL
        SELECT COUNT(*) FROM queue_ticket WHERE department_id = :dept_id AND status NOT IN ('completed', 'cancelled', 'no_show')
    ");
    $checkStmt->execute([':dept_id' => $departmentId]);
    $counts = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    $doctorCount = (int)($counts[0] ?? 0);
    $activeTicketCount = (int)($counts[1] ?? 0);

    if ($doctorCount > 0 || $activeTicketCount > 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete department. It has $doctorCount doctor(s) and $activeTicketCount active ticket(s).",
        ]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM department WHERE department_id = :dept_id RETURNING department_id");
    $stmt->execute([':dept_id' => $departmentId]);
    $deleted = $stmt->fetchColumn();

    if (!$deleted) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Department not found.']);
        exit;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Department deleted successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Delete department error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
exit;
