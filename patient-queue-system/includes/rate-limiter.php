<?php
declare(strict_types=1);

/**
 * OHAQRS - Rate Limiting & Throttling Middleware
 * Prevents brute force attacks and DDoS
 */

class RateLimiter {
    private string $redisKeyPrefix = 'rate_limit:';
    private bool $redisEnabled;
    private ?array $cache = null;
    private string $cacheFile;

    public function __construct(bool $useRedis = false) {
        $this->redisEnabled = $useRedis && getenv('REDIS_ENABLED') === 'true';
        $this->cacheFile = sys_get_temp_dir() . '/ohaqrs_rate_limits.json';
        
        if (!$this->redisEnabled && !$this->cache) {
            $this->loadFileCache();
        }
    }

    /**
     * Check if request should be rate limited
     * @param string $identifier Unique identifier (IP, user_id, email, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     */
    public function allow(string $identifier, int $maxRequests = 100, int $windowSeconds = 60): bool {
        $key = $this->redisKeyPrefix . $identifier;
        $now = time();
        $windowStart = $now - $windowSeconds;

        if ($this->redisEnabled) {
            return $this->allowWithRedis($key, $maxRequests, $windowStart, $now);
        } else {
            return $this->allowWithFileCache($key, $maxRequests, $windowStart, $now);
        }
    }

    private function allowWithRedis(string $key, int $maxRequests, int $windowStart, int $now): bool {
        try {
            $redis = new Redis();
            $redis->connect(
                getenv('REDIS_HOST') ?: '127.0.0.1',
                (int)(getenv('REDIS_PORT') ?: 6379)
            );
            
            if (getenv('REDIS_PASSWORD')) {
                $redis->auth(getenv('REDIS_PASSWORD'));
            }

            // Use a sorted set to track requests
            $redis->zRemRangeByScore($key, 0, $windowStart);
            $count = $redis->zCard($key);

            if ($count >= $maxRequests) {
                $redis->close();
                return false;
            }

            $redis->zAdd($key, $now, $now);
            $redis->expire($key, $this->windowSeconds + 1);
            $redis->close();
            return true;

        } catch (Exception $e) {
            error_log("Redis rate limiting failed: " . $e->getMessage());
            // Fall back to file cache
            return $this->allowWithFileCache($key, $maxRequests, $windowStart, $now);
        }
    }

    private function allowWithFileCache(string $key, int $maxRequests, int $windowStart, int $now): bool {
        if ($this->cache === null) {
            $this->loadFileCache();
        }

        if (!isset($this->cache[$key])) {
            $this->cache[$key] = [];
        }

        // Remove old requests outside the window
        $this->cache[$key] = array_filter($this->cache[$key], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        if (count($this->cache[$key]) >= $maxRequests) {
            return false;
        }

        $this->cache[$key][] = $now;
        $this->saveFileCache();
        return true;
    }

    private function loadFileCache(): void {
        if (file_exists($this->cacheFile)) {
            $content = file_get_contents($this->cacheFile);
            $this->cache = json_decode($content, true) ?? [];
        } else {
            $this->cache = [];
        }
    }

    private function saveFileCache(): void {
        file_put_contents($this->cacheFile, json_encode($this->cache), LOCK_EX);
    }

    /**
     * Get remaining requests for an identifier
     */
    public function getRemaining(string $identifier, int $maxRequests = 100, int $windowSeconds = 60): int {
        $key = $this->redisKeyPrefix . $identifier;
        $now = time();
        $windowStart = $now - $windowSeconds;

        if (!$this->redisEnabled) {
            if ($this->cache === null) {
                $this->loadFileCache();
            }

            if (!isset($this->cache[$key])) {
                return $maxRequests;
            }

            $validRequests = array_filter($this->cache[$key], function($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            });

            return max(0, $maxRequests - count($validRequests));
        }

        return $maxRequests;
    }

    /**
     * Reset rate limit for an identifier
     */
    public function reset(string $identifier): void {
        $key = $this->redisKeyPrefix . $identifier;

        if ($this->redisEnabled) {
            try {
                $redis = new Redis();
                $redis->connect(
                    getenv('REDIS_HOST') ?: '127.0.0.1',
                    (int)(getenv('REDIS_PORT') ?: 6379)
                );
                $redis->del($key);
                $redis->close();
            } catch (Exception $e) {
                error_log("Failed to reset Redis rate limit: " . $e->getMessage());
            }
        } else {
            if ($this->cache === null) {
                $this->loadFileCache();
            }
            unset($this->cache[$key]);
            $this->saveFileCache();
        }
    }
}

/**
 * Get client IP address (handles proxies)
 */
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
