<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Admin Account Seeder
 *
 * Route: GET /actions/seed.php
 * Creates the primary admin account for production use.
 * Safe to re-run: uses ON CONFLICT upsert pattern.
 */



header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use GET.']);
    exit;
}

$algo    = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = defined('PASSWORD_ARGON2ID')
    ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
    : ['cost' => 12];

$adminPassword = password_hash('Kevin@1234', $algo, $options);

$accounts = [
    ['email' => 'kevin@gmail.com', 'role' => 'admin', 'first' => 'Kevin', 'last' => 'Admin', 'phone' => '09170000000', 'dept' => null],
];

$created = [];

try {
    $pdo->beginTransaction();

    foreach ($accounts as $acc) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, role, created_at, updated_at)
             VALUES (:email, :hash, :role, NOW(), NOW())
             ON CONFLICT (email) DO UPDATE
                 SET password_hash = EXCLUDED.password_hash,
                     role = EXCLUDED.role,
                     is_active = TRUE,
                     updated_at = NOW()
             RETURNING id'
        );
        $stmt->execute([
            ':email' => $acc['email'],
            ':hash'  => $adminPassword,
            ':role'  => $acc['role'],
        ]);
        $userId = (int)$stmt->fetchColumn();

        if ($acc['role'] === 'admin') {
            $pdo->prepare(
                'INSERT INTO admin (first_name, last_name, email, phone_number, user_id, created_at)
                 VALUES (:first, :last, :email, :phone, :uid, NOW())
                 ON CONFLICT (user_id) DO UPDATE
                     SET first_name = EXCLUDED.first_name,
                         last_name = EXCLUDED.last_name,
                         phone_number = EXCLUDED.phone_number'
            )->execute([
                ':first' => $acc['first'],
                ':last'  => $acc['last'],
                ':email' => $acc['email'],
                ':phone' => $acc['phone'],
                ':uid'   => $userId,
            ]);
        }

        $created[] = $acc['email'];
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'message'  => 'Admin account seeded successfully.',
        'accounts' => $created,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OHAQRS seed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Seeding failed: ' . $e->getMessage()]);
}
exit;
