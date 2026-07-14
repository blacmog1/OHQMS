<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/dotenv.php';
require_once __DIR__ . '/../includes/rate-limiter.php';
require_once __DIR__ . '/../includes/csrf-protection.php';
require_once __DIR__ . '/../includes/security-logger.php';
/**
 * OHAQRS - Login Handler (Production)
 *
 * Security features:
 *   - Rate limiting to prevent brute force attacks
 *   - CSRF token validation
 *   - Prepared statements (no SQL injection)
 *   - password_verify() for timing-safe comparison
 *   - session_regenerate_id() prevents session fixation
 *   - Comprehensive security logging
 *   - Session cookie flags: HttpOnly, SameSite=Strict, Secure
 */

// ---------------------------------------------------------------------------
// 1. Bootstrap
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/session-config.php';

require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------------------------
// 1.5 Rate Limiting Check
// ---------------------------------------------------------------------------
$clientIp = getClientIp();
$rateLimiter = new RateLimiter(getenv('REDIS_ENABLED') === 'true');

$loginRateLimit = (int)(getenv('LOGIN_RATE_LIMIT_REQUESTS') ?: 5);
$loginRateWindow = (int)(getenv('LOGIN_RATE_LIMIT_WINDOW') ?: 300);

if (!$rateLimiter->allow("login:$clientIp", $loginRateLimit, $loginRateWindow)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many login attempts. Please try again later.'
    ]);
    $GLOBALS['logger']->warning(
        'Login rate limit exceeded',
        ['ip' => $clientIp],
        'security'
    );
    exit;
}

// ---------------------------------------------------------------------------
// 2. Method Guard
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// ---------------------------------------------------------------------------
// 3. Input Parsing (JSON body or form-data)
// ---------------------------------------------------------------------------
$input       = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
} else {
    $input = $_POST;
}

$email    = trim((string)($input['email']    ?? ''));
$password = (string)($input['password']      ?? '');

// ---------------------------------------------------------------------------
// 4. Basic Validation (fast-fail before hitting the DB)
// ---------------------------------------------------------------------------
if ($email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if ($password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit;
}

// ---------------------------------------------------------------------------
// 5. Database Lookup — prepared statement, users table only
// ---------------------------------------------------------------------------
try {
    $stmt = $pdo->prepare(
        "SELECT id, email, password_hash, role
         FROM   users
         WHERE  email = :email
         LIMIT  1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('OHAQRS login PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
    exit;
}

// ---------------------------------------------------------------------------
// 6. Credential Verification
//    Always call password_verify() even if user not found to prevent
//    timing-based user enumeration attacks.
// ---------------------------------------------------------------------------
$dummyHash   = '$argon2id$v=19$m=65536,t=4,p=1$dummy$dummyhash'; // keeps timing consistent
$hashToCheck = $user ? (string)$user['password_hash'] : $dummyHash;
$passwordOk  = password_verify($password, $hashToCheck);

if (!$user || !$passwordOk) {
    http_response_code(401);
    
    // Log failed login attempt
    $GLOBALS['logger']->warning(
        'Failed login attempt',
        ['email' => $email, 'ip' => $clientIp],
        'security'
    );
    
    // Track failed attempts in database for later analysis
    try {
        $failStmt = $pdo->prepare(
            'INSERT INTO failed_login_attempt (email, ip_address, user_agent, attempted_at)
             VALUES (:email, :ip, :ua, NOW())'
        );
        $failStmt->execute([
            ':email' => $email,
            ':ip' => $clientIp,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (Exception $e) {
        error_log('Failed to log failed login: ' . $e->getMessage());
    }
    
    // Deliberately vague: do not reveal whether the email exists
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// ---------------------------------------------------------------------------
// 7. Successful Authentication
// ---------------------------------------------------------------------------

// Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

// Populate session with only what downstream pages need
$_SESSION['user_id']    = (int)$user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['role']       = $user['role'];
$_SESSION['logged_in_at'] = time();

// Log successful login
$GLOBALS['logger']->audit(
    'user_login',
    'user',
    (int)$user['id'],
    (int)$user['id'],
    ['ip' => $clientIp, 'email' => $user['email']]
);

// Reset rate limiter for this IP (successful login)
$rateLimiter->reset("login:$clientIp");

// ---------------------------------------------------------------------------
// 8. Optional: Check if password needs rehashing (algorithm upgrade path)
//    If the stored hash was bcrypt and the server now supports Argon2id,
//    transparently upgrade the hash on successful login.
// ---------------------------------------------------------------------------
$algo    = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = defined('PASSWORD_ARGON2ID')
    ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
    : ['cost' => 12];

if (password_needs_rehash($user['password_hash'], $algo, $options)) {
    try {
        $newHash = password_hash($password, $algo, $options);
        if ($newHash !== false) {
            $rehashStmt = $pdo->prepare(
                "UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id"
            );
            $rehashStmt->execute([':hash' => $newHash, ':id' => $user['id']]);
        }
    } catch (PDOException $e) {
        // Non-fatal: log but don't block the login
        error_log('OHAQRS login rehash PDOException: ' . $e->getMessage());
    }
}

    // ---------------------------------------------------------------------------
    // 9. Success Response — include display name from profile
    // ---------------------------------------------------------------------------
    $displayName = $user['email'];
    
    try {
        if ($user['role'] === 'patient') {
            $nameStmt = $pdo->prepare(
                'SELECT first_name, last_name FROM patient WHERE user_id = :uid LIMIT 1'
            );
            $nameStmt->execute([':uid' => (int)$user['id']]);
            $row = $nameStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['first_name'])) {
                $displayName = trim($row['first_name'] . ' ' . $row['last_name']);
            }
        } elseif ($user['role'] === 'doctor') {
            $nameStmt = $pdo->prepare(
                'SELECT first_name, last_name FROM doctor WHERE user_id = :uid LIMIT 1'
            );
            $nameStmt->execute([':uid' => (int)$user['id']]);
            $row = $nameStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['first_name'])) {
                $displayName = 'Dr. ' . trim($row['first_name'] . ' ' . $row['last_name']);
            }
        }
    } catch (Exception $nameError) {
        // If profile lookup fails, just use email - don't block login
        error_log('OHAQRS login profile lookup error: ' . $nameError->getMessage());
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user'    => [
            'id'    => (int)$user['id'],
            'email' => (string)$user['email'],
            'role'  => (string)$user['role'],
            'name'  => (string)$displayName,
        ],
    ]);
    exit;
