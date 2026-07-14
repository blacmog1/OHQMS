<?php
declare(strict_types=1);

/**
 * OHAQRS - PostgreSQL Wrapper (for Neon Cloud SNI issues)
 * Uses psql command-line tool as a fallback when PDO fails
 */

class PostgresQLWrapper {
    private $host;
    private $user;
    private $pass;
    private $name;
    
    public function __construct($host, $user, $pass, $name) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->name = $name;
    }
    
    public function prepare($sql) {
        $stmt = new PostgresQLStatement($sql, $this->host, $this->user, $this->pass, $this->name);
        return $stmt;
    }
    
    public function query($sql) {
        $stmt = new PostgresQLStatement($sql, $this->host, $this->user, $this->pass, $this->name);
        $stmt->executeQuery();
        return $stmt;
    }
    
    public function exec($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute([]);
        return true;
    }
}

class PostgresQLStatement {
    private $sql;
    private $host;
    private $user;
    private $pass;
    private $name;
    private $result = [];
    private $rowIndex = 0;
    
    public function __construct($sql, $host, $user, $pass, $name) {
        $this->sql = $sql;
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->name = $name;
    }
    
    public function executeQuery() {
        $this->execute([]);
        return $this;
    }
    
    public function execute($params = []) {
        $sql = $this->sql;
        
        foreach ($params as $k => $v) {
            $key = is_int($k) ? '$' . ($k + 1) : ':' . ltrim($k, ':');
            $escaped = "'" . str_replace("'", "''", (string)$v) . "'";
            $sql = str_replace($key, $escaped, $sql);
        }
        
        putenv('PGPASSWORD=' . $this->pass);
        $psqlPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe';
        
        $cmd = sprintf(
            '"%s" -h %s -U %s -d %s -P tuples_only=off -A -F "|" -c %s 2>&1',
            $psqlPath,
            escapeshellarg($this->host),
            escapeshellarg($this->user),
            escapeshellarg($this->name),
            escapeshellarg($sql)
        );
        
        $output = shell_exec($cmd) ?? '';
        
        if (strpos($output, 'ERROR') !== false) {
            throw new PDOException('Database query failed: ' . trim($output));
        }
        
        $lines = array_filter(explode("\n", trim($output)));
        $this->result = [];
        $this->rowIndex = 0;
        
        if (empty($lines)) {
            return true;
        }
        
        $columnLine = array_shift($lines);
        $columns = explode('|', $columnLine);
        $columns = array_map('trim', $columns);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parts = explode('|', $line);
            $row = [];
            for ($i = 0; $i < count($columns); $i++) {
                $value = isset($parts[$i]) ? trim($parts[$i]) : '';
                $row[$columns[$i]] = $value;
                $row[strval($i)] = $value;
            }
            $this->result[] = $row;
        }
        
        return true;
    }
    
    public function fetch($fetchMode = PDO::FETCH_ASSOC) {
        if ($this->rowIndex >= count($this->result)) {
            return null;
        }
        return $this->result[$this->rowIndex++];
    }
    
    public function fetchAll($fetchMode = PDO::FETCH_ASSOC) {
        $this->rowIndex = 0;
        return $this->result;
    }
    
    public function fetchColumn($columnIndex = 0) {
        if (empty($this->result)) {
            return null;
        }
        $row = $this->result[0];
        $key = isset($row[strval($columnIndex)]) ? strval($columnIndex) : 0;
        return $row[$key] ?? null;
    }
}
