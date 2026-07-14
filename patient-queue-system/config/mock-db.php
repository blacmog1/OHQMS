<?php
declare(strict_types=1);

/**
 * OHAQRS - Mock Database (for testing without live database)
 * Used when the actual database connection fails
 * Returns fixture data to allow testing of the system
 */

class MockDatabase {
    private array $users = [
        ['id' => 1, 'email' => 'patient1@hospital.local', 'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2', 'role' => 'patient', 'first_name' => 'John', 'last_name' => 'Doe'],
        ['id' => 2, 'email' => 'doctor@hospital.local', 'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2', 'role' => 'doctor', 'first_name' => 'Dr.', 'last_name' => 'Smith'],
        ['id' => 3, 'email' => 'admin@hospital.local', 'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$YUhKNE9IQndRM05sYjJGdQ$1dUqCG1P4C3R7v8qPLw9Tw9L5nP5K6M7N8O9P0Q1R2', 'role' => 'admin', 'first_name' => 'Admin', 'last_name' => 'User'],
    ];
    
    private array $departments = [
        ['id' => 1, 'name' => 'General', 'prefix' => 'GEN'],
        ['id' => 2, 'name' => 'Pediatrics', 'prefix' => 'PED'],
        ['id' => 3, 'name' => 'Cardiology', 'prefix' => 'CAR'],
    ];
    
    public function prepare(string $sql) {
        return new MockStatement($sql, $this->users, $this->departments);
    }
}

class MockStatement {
    private string $sql;
    private array $users;
    private array $departments;
    private array $params = [];
    
    public function __construct(string $sql, array $users, array $departments) {
        $this->sql = $sql;
        $this->users = $users;
        $this->departments = $departments;
    }
    
    public function execute(array $params = []): bool {
        $this->params = $params;
        return true;
    }
    
    public function fetch(): ?array {
        if (stripos($this->sql, 'FROM users') !== false) {
            if (!empty($this->params)) {
                foreach ($this->users as $user) {
                    if ($user['email'] === $this->params[0] || $user['email'] === ($this->params['email'] ?? null)) {
                        return $user;
                    }
                }
            }
            return $this->users[0] ?? null;
        }
        return null;
    }
    
    public function fetchAll(): array {
        if (stripos($this->sql, 'FROM departments') !== false) {
            return $this->departments;
        }
        return [];
    }
}
