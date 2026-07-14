<?php
declare(strict_types=1);

/**
 * OHAQRS - Database Query Wrapper
 * Uses PostgreSQL command-line tools when PDO fails
 * 
 * This is a workaround for SNI certificate issues with Neon Cloud
 */

class DatabaseWrapper {
    private ?array $pdo = null;
    private string $host;
    private string $port;
    private string $user;
    private string $password;
    private string $database;
    
    public function __construct(string $host, string $port, string $user, string $password, string $database) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
    }
    
    /**
     * Execute a SELECT query and return all rows
     */
    public function fetchAll(string $query): array {
        return json_decode($this->executeViaCommand($query), true) ?? [];
    }
    
    /**
     * Execute a SELECT query and return single row
     */
    public function fetch(string $query): ?array {
        $results = $this->fetchAll($query);
        return $results[0] ?? null;
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE and return affected rows
     */
    public function execute(string $query): int {
        $output = $this->executeViaCommand($query);
        // Parse psql output for affected rows
        if (preg_match('/(\d+)\s+rows?\s+affected/', $output, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
    
    /**
     * Execute query via command-line psql
     */
    private function executeViaCommand(string $query): string {
        $psqlPath = 'C:\Program Files\PostgreSQL\18\bin\psql.exe';
        
        // Escape query for shell
        $escapedQuery = str_replace('"', '\\"', $query);
        
        $command = sprintf(
            '%s -h %s -U %s -d %s -c "%s" --json',
            escapeshellarg($psqlPath),
            escapeshellarg($this->host),
            escapeshellarg($this->user),
            escapeshellarg($this->database),
            $escapedQuery
        );
        
        putenv('PGPASSWORD=' . $this->password);
        
        $output = shell_exec($command . ' 2>&1');
        
        if ($output === null || strpos($output, 'ERROR') !== false) {
            throw new RuntimeException('Database query failed: ' . ($output ?? 'Unknown error'));
        }
        
        return $output;
    }
}

// For now, just use direct psql for critical queries
function queryDatabase(string $sql, array $params = []): array {
    $psqlPath = 'C:\Program Files\PostgreSQL\18\bin\psql.exe';
    $host = getenv('PGHOST') ?: 'ep-polished-band-ataoop94.c-9.us-east-1.aws.neon.tech';
    $user = getenv('PGUSER') ?: 'neondb_owner';
    $password = getenv('PGPASSWORD') ?: '';
    $database = getenv('PGDATABASE') ?: 'neondb';
    
    // Simple query execution - for testing
    putenv('PGPASSWORD=' . $password);
    
    $cmd = $psqlPath . ' -h ' . escapeshellarg($host) . ' -U ' . escapeshellarg($user) . ' -d ' . escapeshellarg($database) . ' -t -c ' . escapeshellarg($sql);
    
    $output = shell_exec($cmd . ' 2>&1');
    
    return $output ? array_map('trim', explode("\n", trim($output))) : [];
}
