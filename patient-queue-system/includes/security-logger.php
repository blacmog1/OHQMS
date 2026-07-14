<?php
declare(strict_types=1);

/**
 * OHAQRS - Comprehensive Logging System
 * Centralized logging for security, errors, and audit trails
 */

class Logger {
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    const LEVEL_AUDIT = 'AUDIT';

    private string $logPath;
    private string $logLevel;
    private bool $logToFile = true;
    private bool $logToDatabase = false;
    private ?PDO $pdo = null;
    private array $logLevels = [self::LEVEL_DEBUG => 0, self::LEVEL_INFO => 1, self::LEVEL_WARNING => 2, self::LEVEL_ERROR => 3, self::LEVEL_CRITICAL => 4, self::LEVEL_AUDIT => 5];

    public function __construct(?PDO $pdo = null, string $logPath = '/tmp', string $logLevel = 'INFO') {
        $this->pdo = $pdo;
        $this->logPath = $logPath;
        $this->logLevel = $logLevel;

        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }

        $this->logToDatabase = $pdo !== null;
    }

    /**
     * Log a message at the specified level
     */
    public function log(string $level, string $message, array $context = [], string $category = 'general'): void {
        // Check if we should log this level
        if ($this->logLevels[$level] < $this->logLevels[$this->logLevel]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context) : '';

        // Log to file
        if ($this->logToFile) {
            $this->logToFile($level, $message, $category, $timestamp, $contextJson);
        }

        // Log to database (for audit trail)
        if ($this->logToDatabase && $level === self::LEVEL_AUDIT) {
            $this->logToDb($level, $message, $category, $context);
        }
    }

    public function debug(string $message, array $context = [], string $category = 'general'): void {
        $this->log(self::LEVEL_DEBUG, $message, $context, $category);
    }

    public function info(string $message, array $context = [], string $category = 'general'): void {
        $this->log(self::LEVEL_INFO, $message, $context, $category);
    }

    public function warning(string $message, array $context = [], string $category = 'general'): void {
        $this->log(self::LEVEL_WARNING, $message, $context, $category);
    }

    public function error(string $message, array $context = [], string $category = 'general'): void {
        $this->log(self::LEVEL_ERROR, $message, $context, $category);
    }

    public function critical(string $message, array $context = [], string $category = 'general'): void {
        $this->log(self::LEVEL_CRITICAL, $message, $context, $category);
    }

    /**
     * Audit log - for security and compliance
     */
    public function audit(string $action, string $entity, ?int $entityId = null, ?int $userId = null, array $details = []): void {
        $context = [
            'entity' => $entity,
            'entity_id' => $entityId,
            'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
            'ip_address' => getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $details,
        ];

        $message = "$action on $entity";
        if ($entityId) {
            $message .= " (ID: $entityId)";
        }

        $this->log(self::LEVEL_AUDIT, $message, $context, 'audit');
    }

    private function logToFile(string $level, string $message, string $category, string $timestamp, string $context): void {
        $logFile = $this->logPath . '/ohaqrs_' . $category . '.log';

        $logEntry = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $category,
            $message,
            $context ? " | Context: $context" : ''
        );

        $this->rotateLogFile($logFile);
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function rotateLogFile(string $logFile): void {
        $maxSize = (int)(getenv('LOG_MAX_SIZE') ?: 10485760); // 10MB default
        $maxFiles = (int)(getenv('LOG_MAX_FILES') ?: 10);

        if (!file_exists($logFile) || filesize($logFile) < $maxSize) {
            return;
        }

        $logDir = dirname($logFile);
        $logBasename = basename($logFile, '.log');

        // Rotate existing files
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = "$logDir/{$logBasename}.$i.log";
            $newFile = "$logDir/{$logBasename}." . ($i + 1) . ".log";

            if (file_exists($oldFile)) {
                if ($i === $maxFiles - 1) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        rename($logFile, "$logDir/$logBasename.1.log");
    }

    private function logToDb(string $level, string $message, string $category, array $context): void {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                 VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, NOW())'
            );

            $stmt->execute([
                ':user_id' => $context['user_id'] ?? null,
                ':action' => $message,
                ':entity_type' => $context['entity'] ?? $category,
                ':entity_id' => $context['entity_id'] ?? null,
                ':details' => json_encode($context),
                ':ip_address' => $context['ip_address'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log("Failed to write audit log to database: " . $e->getMessage());
        }
    }
}

/**
 * Get client IP address (handles proxies)
 */
if (!function_exists('getClientIp')) {
    function getClientIp(): string {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Initialize global logger instance
if (!isset($GLOBALS['logger'])) {
    $logPath = getenv('LOG_PATH') ?: sys_get_temp_dir() . '/ohaqrs';
    $logLevel = getenv('LOG_LEVEL') ?: 'INFO';
    
    try {
        global $pdo;
        $GLOBALS['logger'] = new Logger($pdo ?? null, $logPath, $logLevel);
    } catch (Exception $e) {
        error_log("Failed to initialize logger: " . $e->getMessage());
        // Create a dummy logger that just uses error_log
        class DummyLogger {
            public function __call($method, $args) { return null; }
        }
        $GLOBALS['logger'] = new DummyLogger();
    }
}
