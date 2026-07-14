<?php
declare(strict_types=1);

/**
 * Patient Queue Management System - Access Control Guard
 * 
 * Reusable security script to check user session authentication and role-based permissions.
 */

// Start session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current user has one of the allowed roles.
 * Redirects to the login page if the user is not authenticated.
 * Redirects to the access-denied page if the user does not have permission.
 *
 * @param array $allowedRoles Array of allowed role names (e.g., ['admin', 'doctor', 'patient'])
 * @return void
 */
function checkAccess(array $allowedRoles) {
    // Dynamically calculate the project root path relative to the server document root.
    // This ensures redirects work correctly whether hosted at the domain root or inside a XAMPP subfolder.
    $projectRoot = '/';
    $includesDir = str_replace('\\', '/', __DIR__);
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($docRoot !== '') {
        $projectPath = str_replace($docRoot, '', dirname($includesDir));
        $projectRoot = '/' . trim($projectPath, '/') . '/';
        // Normalize double slashes
        $projectRoot = str_replace('//', '/', $projectRoot);
    }

    $loginUrl = $projectRoot . 'login.php';
    $accessDeniedUrl = $projectRoot . 'access-denied.php';

    // 1. Inspect if the user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $loginUrl);
        exit;
    }

    // 2. Inspect if the user's role is set and allowed
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $allowedRoles, true)) {
        header("Location: " . $accessDeniedUrl);
        exit;
    }
}
