<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $isLocalhost = preg_match('#^(localhost|127\.0\.0\.1|::1)(:\d+)?$#', $host) === 1;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    if ($isLocalhost) {
        $samesite = 'Lax';
        $secure = false;
    } else {
        $samesite = (string)(getenv('SESSION_SAMESITE') ?: 'None');
        if (!in_array($samesite, ['Lax', 'Strict', 'None'], true)) {
            $samesite = 'None';
        }
        $secure = $isHttps;
        if (getenv('SESSION_SECURE') !== false) {
            $secure = filter_var(getenv('SESSION_SECURE'), FILTER_VALIDATE_BOOLEAN);
        }
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $samesite,
    ]);

    session_start();
}
