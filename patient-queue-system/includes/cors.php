<?php
declare(strict_types=1);

/**
 * OHAQRS - CORS Handler (Production-Ready)
 *
 * Handles Cross-Origin Resource Sharing with configurable origins from .env
 */

require_once __DIR__ . '/dotenv.php';

// Initialize DotEnv if not already done
if (!getenv('DB_PROVIDER')) {
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $dotenv = new DotEnv($envFile);
        $dotenv->load();
    }
}

// Get allowed origins from environment
$allowedOriginsEnv = getenv('CORS_ALLOWED_ORIGINS') ?: '';
$allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsEnv)));

// Default origins if none specified
if (empty($allowedOrigins)) {
    $allowedOrigins = [
        'https://development-of-an-automated-patient.vercel.app',
        'http://localhost:5173',
        'http://localhost:3000',
    ];
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Allow configured origins
$allowed = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')));

// Development fallback: allow any localhost origin
$isDevLocalhost = preg_match('#^https?://localhost(:\d+)?$#', $origin) === 1;

if (in_array($origin, $allowed, true) || $isDevLocalhost) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Expose-Headers: X-RateLimit-Remaining, X-RateLimit-Reset');
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
