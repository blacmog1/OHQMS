<?php
declare(strict_types=1);

/**
 * OHAQRS - Environment Loader (DotEnv)
 * Loads environment variables from .env file
 */

class DotEnv {
    private array $env = [];

    public function __construct(string $filePath) {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Environment file not found: $filePath");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                $this->env[$key] = $value;
            }
        }
    }

    public function get(string $key, $default = null) {
        return $this->env[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public function getAll(): array {
        return $this->env;
    }

    public function load(): void {
        foreach ($this->env as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Load .env file
$projectRoot = dirname(dirname(__FILE__));
$envFile = $projectRoot . '/.env';

if (file_exists($envFile)) {
    try {
        $dotEnv = new DotEnv($envFile);
        $dotEnv->load();
    } catch (RuntimeException $e) {
        error_log("Failed to load .env: " . $e->getMessage());
    }
}
