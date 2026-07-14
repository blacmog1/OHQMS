<?php
declare(strict_types=1);

/**
 * OHAQRS - CSRF Token Protection
 * Prevents Cross-Site Request Forgery attacks
 */

class CSRFTokenManager {
    private string $tokenKey = '_csrf_token';
    private string $sessionKey = '_csrf_session_token';
    private int $tokenLength = 32;

    public function __construct() {
        $this->tokenLength = (int)(getenv('CSRF_TOKEN_LENGTH') ?: 32);
    }

    /**
     * Generate and store a CSRF token
     */
    public function generateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate a random token
        $token = bin2hex(random_bytes($this->tokenLength / 2));

        // Store in session (both plaintext and hashed for comparison)
        $_SESSION[$this->sessionKey] = $token;

        return $token;
    }

    /**
     * Get the current CSRF token (generate if not exists)
     */
    public function getToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->sessionKey])) {
            return $this->generateToken();
        }

        return $_SESSION[$this->sessionKey];
    }

    /**
     * Verify a CSRF token
     */
    public function verifyToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[$this->sessionKey] ?? null;

        if (!$sessionToken) {
            return false;
        }

        // Use hash_equals for timing-safe comparison
        return hash_equals($sessionToken, $token);
    }

    /**
     * Regenerate token (after sensitive operations)
     */
    public function regenerateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[$this->sessionKey]);
        return $this->generateToken();
    }

    /**
     * Validate CSRF token from request
     * Checks POST body, header, or query parameter
     */
    public static function validateRequest(): bool {
        $manager = new self();
        $token = null;

        // Check POST body first
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (stripos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
                $token = $input['csrf_token'] ?? null;
            } else {
                $token = $_POST['csrf_token'] ?? null;
            }
        }

        // Check X-CSRF-Token header
        if (!$token) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        // For GET requests (optional security), check query parameter
        if (!$token && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $token = $_GET['csrf_token'] ?? null;
        }

        if (!$token) {
            return false;
        }

        return $manager->verifyToken($token);
    }
}

/**
 * Helper function to get CSRF token
 */
function getCsrfToken(): string {
    $manager = new CSRFTokenManager();
    return $manager->getToken();
}

/**
 * Helper function to output CSRF token in HTML form
 */
function csrfTokenHiddenField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
