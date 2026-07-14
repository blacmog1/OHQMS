#!/bin/bash
set -e

# Set Apache port from $PORT env variable (Render default is 10000, fallback to 80)
PORT_NUM=${PORT:-80}
echo "Configuring Apache to listen on port $PORT_NUM"
sed -i "s/Listen 80/Listen ${PORT_NUM}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT_NUM}>/g" /etc/apache2/sites-available/000-default.conf

# Generate config/db.php from environment variables if not present
if [ ! -f /var/www/html/config/db.php ]; then
  echo "Generating config/db.php from environment variables..."
  cat <<EOF > /var/www/html/config/db.php
<?php
define('DB_HOST', '$(echo $DB_HOST)');
define('DB_PORT', '$(echo ${DB_PORT:-5432})');
define('DB_USER', '$(echo $DB_USER)');
define('DB_PASS', '$(echo $DB_PASS)');
define('DB_NAME', '$(echo ${DB_NAME:-neondb})');

// Include CORS configuration
\$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost:3000',
];

if (isset(\$_SERVER['HTTP_ORIGIN'])) {
    \$origin = \$_SERVER['HTTP_ORIGIN'];
    // Allow localhost, custom vercel URLs, or any other configured frontend
    if (in_array(\$origin, \$allowedOrigins, true) || preg_match('/https:\/\/.*\.vercel\.app\$/', \$origin)) {
        header("Access-Control-Allow-Origin: " . \$origin);
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");
    }
}

if (\$_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset(\$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    }
    if (isset(\$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {\$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

try {
    \$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException \$e) {
    die("Cloud Database Connection Failure: " . \$e->getMessage());
}
EOF
fi

# Run Apache in foreground
exec apache2-foreground
