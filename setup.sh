#!/bin/bash

# OHAQRS - Automated Setup Script
# This script helps set up the OHAQRS Hospital Queue System

set -e

echo "=========================================="
echo "OHAQRS Hospital Queue System - Setup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check PHP version
echo "Checking PHP version..."
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "PHP Version: $PHP_VERSION"

if ! php -m | grep -q "pdo_pgsql"; then
    echo -e "${RED}Error: pdo_pgsql extension not installed${NC}"
    exit 1
fi

if ! php -m | grep -q "pgsql"; then
    echo -e "${RED}Error: pgsql extension not installed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Required PHP extensions found${NC}"
echo ""

# Create .env from example if it doesn't exist
if [ ! -f "patient-queue-system/.env" ]; then
    echo "Creating .env file..."
    cp patient-queue-system/.env.example patient-queue-system/.env
    echo -e "${YELLOW}Please edit patient-queue-system/.env with your database credentials${NC}"
    exit 1
fi

# Create log directory
LOG_PATH="/var/log/ohaqrs"
if [ ! -d "$LOG_PATH" ]; then
    echo "Creating log directory at $LOG_PATH..."
    sudo mkdir -p $LOG_PATH
    sudo chmod 755 $LOG_PATH
    echo -e "${GREEN}✓ Log directory created${NC}"
fi

# Test database connection
echo ""
echo "Testing database connection..."
php -r "
require_once 'patient-queue-system/config/db.php';
try {
    \$pdo->query('SELECT 1');
    echo \"Database connection successful\n\";
} catch (PDOException \$e) {
    echo \"Database connection failed: \" . \$e->getMessage() . \"\n\";
    exit(1);
}
"

echo -e "${GREEN}✓ Database connection successful${NC}"
echo ""

# Install frontend dependencies
echo "Installing frontend dependencies..."
cd patient-queue-system/frontend
if command -v pnpm &> /dev/null; then
    pnpm install
else
    npm install
fi
cd ../..
echo -e "${GREEN}✓ Frontend dependencies installed${NC}"
echo ""

# Create necessary directories
echo "Creating necessary directories..."
mkdir -p patient-queue-system/templates/emails
mkdir -p /tmp/ohaqrs_logs
echo -e "${GREEN}✓ Directories created${NC}"
echo ""

# Generate CSRF and JWT secrets
echo "Generating security keys..."
CSRF_KEY=$(head -c 32 /dev/urandom | base64)
JWT_KEY=$(head -c 64 /dev/urandom | base64)

echo "Add these to your .env file if not already set:"
echo "CSRF_TOKEN_LENGTH=32"
echo "JWT_SECRET=$JWT_KEY"
echo ""

# Create initial admin (optional)
read -p "Do you want to create an admin account? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -p "Admin email: " ADMIN_EMAIL
    read -s -p "Admin password: " ADMIN_PASS
    echo ""
    
    php -r "
    require_once 'patient-queue-system/config/db.php';
    require_once 'patient-queue-system/includes/dotenv.php';
    
    \$email = '$ADMIN_EMAIL';
    \$password = '$ADMIN_PASS';
    
    \$hash = password_hash(\$password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 1
    ]);
    
    \$stmt = \$pdo->prepare(
        'INSERT INTO users (email, password_hash, role, created_at, updated_at)
         VALUES (:email, :hash, :role, NOW(), NOW())
         ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash'
    );
    
    \$stmt->execute([
        ':email' => \$email,
        ':hash' => \$hash,
        ':role' => 'admin'
    ]);
    
    echo \"Admin account created successfully\n\";
    "
fi

echo ""
echo -e "${GREEN}=========================================="
echo "Setup Complete!"
echo "==========================================${NC}"
echo ""
echo "Next steps:"
echo "1. Start PHP server: php -S 127.0.0.1:8000 -t patient-queue-system"
echo "2. Start frontend: cd patient-queue-system/frontend && npm run dev"
echo "3. Access at: http://localhost:5173"
echo ""
echo "For production deployment, see PRODUCTION_SETUP.md"
