<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
/**
 * OHAQRS - Logout Handler
 *
 * Route:  POST /actions/logout.php
 * Access: Any authenticated user
 *
 * Destroys the server-side session, clears the session cookie,
 * and returns a JSON confirmation. Safe to call even if not logged in.
 */



header('Content-Type: application/json; charset=utf-8');

// Start session if it exists so we can destroy it
require_once __DIR__ . '/../includes/session-config.php';

// Clear all session variables
$_SESSION = [];

// Expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the server-side session
session_destroy();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'You have been logged out successfully.',
]);
exit;
