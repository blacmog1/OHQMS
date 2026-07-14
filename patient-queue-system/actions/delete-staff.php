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

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($decoded)) { $input = $decoded; }
} else {
    $input = $_POST;
}

$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;

if ($userId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $role = $stmt->fetchColumn();

    if ($role === 'doctor') {
        $stmt = $pdo->prepare("DELETE FROM doctor WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Staff member removed successfully.',
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('Delete staff error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
