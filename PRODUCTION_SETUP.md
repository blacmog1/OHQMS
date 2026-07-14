# OHAQRS - Production Setup & Deployment Guide

## ✅ Prerequisites

### System Requirements
- **PHP**: 8.1 or higher with PDO PostgreSQL extension
- **PostgreSQL**: 12 or higher
- **Node.js**: 18 or higher
- **npm** or **pnpm**: Package manager
- **Git**: For version control

### Required PHP Extensions
- `pdo_pgsql` - PostgreSQL database driver
- `pgsql` - PostgreSQL client library
- `json` - JSON support
- `curl` - HTTP requests (optional, for email)
- `openssl` - SSL/TLS support

## 🚀 Installation Steps

### 1. Database Setup

#### Option A: PostgreSQL on Local Machine
```bash
# Create database
createdb hospital_queue

# Run migrations in order
psql hospital_queue < schema/01_create_database.sql
psql hospital_queue < schema/02_schema.sql
psql hospital_queue < schema/03_functions_triggers.sql
psql hospital_queue < schema/04_auth_users.sql
psql hospital_queue < schema/05_seed_demo.sql
psql hospital_queue < schema/06_security_audit_tables.sql
psql hospital_queue < schema/07_doctor_schedules_and_feedback.sql
```

#### Option B: PostgreSQL on Neon Cloud (Recommended for Production)
1. Sign up at [neon.tech](https://neon.tech)
2. Create a new project
3. Copy the connection string
4. Run migrations in Neon dashboard or via psql

### 2. Backend Configuration

#### Copy and Configure .env File
```bash
# Copy the example file
cp .env.example .env

# Edit .env with your settings
nano .env
```

#### Key Configuration Values
```env
# Database
DB_HOST=your-postgres-host
DB_PORT=5432
DB_USER=your-user
DB_PASS=your-password
DB_NAME=hospital_queue

# Security
CSRF_TOKEN_LENGTH=32
SESSION_SECURE=true    # Use true in production
SESSION_SAMESITE=Strict

# Rate Limiting
RATE_LIMIT_ENABLED=true
LOGIN_RATE_LIMIT_REQUESTS=5
LOGIN_RATE_LIMIT_WINDOW=300

# Email Notifications
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_FROM_ADDRESS=noreply@yourhospital.com

# Logging
LOG_LEVEL=info
LOG_PATH=/var/log/ohaqrs
```

### 3. PHP Backend Setup

#### Enable PHP Extensions (XAMPP on Windows)
```ini
# Edit: C:\xampp\php\php.ini
# Find and uncomment these lines:
extension=pdo_pgsql
extension=pgsql
```

#### Start PHP Server
```bash
# Option 1: Built-in PHP Server (Development)
cd patient-queue-system
php -S 127.0.0.1:8000

# Option 2: Apache with XAMPP (Production-Ready)
# Copy folder to htdocs
cp -r patient-queue-system C:\xampp\htdocs\

# Enable mod_rewrite in Apache config
# Access via http://localhost/patient-queue-system
```

#### Create Log Directory (Production)
```bash
# Linux/Mac
mkdir -p /var/log/ohaqrs
chmod 755 /var/log/ohaqrs
chown www-data:www-data /var/log/ohaqrs

# Windows
mkdir C:\logs\ohaqrs
```

### 4. Frontend Setup

#### Install Dependencies
```bash
cd patient-queue-system/frontend
npm install
# or
pnpm install
```

#### Configure Environment Variables
```bash
# Create .env.local
cat > .env.local << EOF
VITE_API_BASE_URL=http://127.0.0.1:8000
EOF

# For production (Vercel, etc.)
cat > .env.production << EOF
VITE_API_BASE_URL=https://your-backend-api.com
EOF
```

#### Build Frontend
```bash
# Development
npm run dev

# Production
npm run build
```

### 5. Database Seeding

#### Create Demo Accounts
```bash
# Visit in browser or via curl
curl http://127.0.0.1:8000/actions/seed.php
```

#### Demo Login Credentials
| Role | Email | Password |
|------|-------|----------|
| Patient | patient@demo.com | demo1234 |
| Receptionist | reception@demo.com | demo1234 |
| Admin | admin@demo.com | demo1234 |
| Doctor | doctor@demo.com | demo1234 |

## 🔒 Security Hardening (Production)

### 1. HTTPS/SSL Configuration
```bash
# Generate self-signed certificate (testing only)
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/server.key \
  -out /etc/ssl/certs/server.crt

# Use Let's Encrypt for production
certbot certonly --standalone -d yourdomain.com
```

### 2. Apache Virtual Host Configuration
```apache
<VirtualHost *:443>
    ServerName yourhospital.com
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/server.crt
    SSLCertificateKeyFile /etc/ssl/private/server.key
    
    # Security Headers
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "no-referrer"
    Header set Permissions-Policy "geolocation=(), microphone=()"
    
    DocumentRoot /var/www/hospital-queue
    
    <Directory /var/www/hospital-queue>
        AllowOverride All
        Require all granted
        
        # Disable direct access to sensitive files
        <FilesMatch "\.env|\.git|config">
            Require all denied
        </FilesMatch>
    </Directory>
    
    # Redirect HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName yourhospital.com
    Redirect permanent / https://yourhospital.com/
</VirtualHost>
```

### 3. PHP Security Configuration
```ini
; /etc/php/8.1/apache2/php.ini
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Session security
session.secure = 1
session.httponly = 1
session.samesite = Strict
session.save_path = /var/lib/php/sessions
session.gc_maxlifetime = 3600

; Upload security
upload_max_filesize = 10M
post_max_size = 10M
upload_tmp_dir = /var/tmp
```

### 4. Firewall Rules (UFW on Linux)
```bash
# Allow SSH
ufw allow 22/tcp

# Allow HTTP
ufw allow 80/tcp

# Allow HTTPS
ufw allow 443/tcp

# Deny all other inbound traffic
ufw default deny incoming
ufw default allow outgoing
ufw enable
```

### 5. Database Security
```sql
-- Create limited user for application
CREATE USER app_user WITH PASSWORD 'strong_random_password';
GRANT CONNECT ON DATABASE hospital_queue TO app_user;
GRANT USAGE ON SCHEMA public TO app_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO app_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO app_user;

-- Restrict admin user
ALTER USER postgres WITH PASSWORD 'very_strong_password';
```

## 📊 Monitoring & Maintenance

### 1. Check System Health
```bash
# View logs
tail -f /var/log/ohaqrs/ohaqrs_audit.log
tail -f /var/log/ohaqrs/ohaqrs_security.log
tail -f /var/log/php_errors.log

# Check database connections
psql hospital_queue -c "SELECT count(*) FROM pg_stat_activity;"

# Check disk space
df -h

# Monitor processes
top
```

### 2. Backup Strategy
```bash
# Daily database backup
0 2 * * * pg_dump hospital_queue > /backups/hospital_queue_$(date +\%Y\%m\%d).sql

# Weekly encrypted backup to cloud
0 3 * * 0 tar czf /backups/hospital_queue_$(date +\%Y\%m\%d).tar.gz /var/www/hospital-queue && \
  gpg --symmetric /backups/hospital_queue_$(date +\%Y\%m\%d).tar.gz && \
  aws s3 cp /backups/hospital_queue_$(date +\%Y\%m\%d).tar.gz.gpg s3://hospital-backups/
```

### 3. Database Maintenance
```bash
# Weekly VACUUM and ANALYZE
0 1 * * 0 psql hospital_queue -c "VACUUM ANALYZE;"

# Monthly reindex
0 2 1 * * psql hospital_queue -c "REINDEX DATABASE hospital_queue;"
```

## 🐛 Troubleshooting

### Database Connection Failed
```
Error: SQLSTATE[08006] could not translate host name

Fix:
1. Check DB_HOST is correct
2. Verify PostgreSQL is running
3. Check network connectivity: ping postgres-host
4. Check credentials in .env
```

### CORS Errors
```
Error: Access-Control-Allow-Origin header missing

Fix:
1. Verify CORS_ALLOWED_ORIGINS in .env
2. Check browser console for exact error
3. Ensure frontend URL is in allowed origins list
```

### Rate Limiting Issues
```
Error: 429 Too Many Requests

Fix:
1. Check RATE_LIMIT_ENABLED in .env
2. Clear rate limit cache: rm /tmp/ohaqrs_rate_limits.json
3. Increase limits if needed in .env
```

### Email Not Sending
```
Fix:
1. Check MAIL_DRIVER setting
2. If SMTP, verify credentials
3. Check MAIL_FROM_ADDRESS is valid
4. Look in /tmp/ohaqrs_emails.log for logged emails
```

## 📈 Performance Optimization

### 1. Enable Database Query Caching
```php
// In .env
REDIS_ENABLED=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 2. Frontend Optimization
```bash
# Analyze bundle size
npm run build -- --analyze

# Enable gzip compression in Apache
mod_deflate configuration for .js, .css files
```

### 3. Database Indexing
```sql
-- Verify indexes are being used
EXPLAIN ANALYZE SELECT * FROM queue_ticket 
WHERE department_id = 1 AND status != 'completed';

-- Create missing indexes if needed
CREATE INDEX idx_custom ON table_name(column_name);
```

## 🎓 API Documentation

### Authentication
All endpoints require session authentication except `/actions/login.php` and `/actions/register.php`.

### Core Endpoints

**Login**
```
POST /actions/login.php
Body: { email, password }
Response: { success, user: { id, name, email, role } }
```

**Book Appointment**
```
POST /actions/book-appointment.php
Body: { department_id, entry_channel, doctor_id?, scheduled_slot_at? }
Response: { success, ticket: { ticket_id, ticket_code } }
```

**Reschedule Appointment**
```
POST /actions/reschedule-appointment.php
Body: { ticket_id, new_scheduled_slot, reason? }
Response: { success, new_slot }
```

**Get Queue Analytics** (Admin only)
```
GET /actions/get-queue-analytics.php?period=day&start_date=2024-01-01&end_date=2024-01-31
Response: { overall_metrics, department_metrics, doctor_metrics, ... }
```

**Manage Doctor Schedule**
```
GET /actions/manage-doctor-schedule.php?doctor_id=1&date=2024-01-15
POST /actions/manage-doctor-schedule.php (create)
PUT /actions/manage-doctor-schedule.php (update)
DELETE /actions/manage-doctor-schedule.php?schedule_id=1
```

See [API_REFERENCE.md](./API_REFERENCE.md) for complete documentation.

## 📝 License

This project is proprietary software for hospital queue management. All rights reserved.

## 📞 Support

For issues and support, contact: support@ohaqrs.hospital
