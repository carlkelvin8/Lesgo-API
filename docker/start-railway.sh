#!/bin/bash
set -e

echo "==> RAILWAY STARTUP - LeSGo API"
echo "==> PORT: ${PORT:-8080}"
echo "==> Environment: ${APP_ENV:-unknown}"

PORT=${PORT:-8080}

# Validate APP_KEY
if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY not set"
    exit 1
fi

echo "==> APP_KEY configured: ${APP_KEY:0:15}..."

# Create nginx config directly (no template)
echo "==> Creating nginx configuration for port ${PORT}"
cat > /etc/nginx/sites-available/default << EOF
server {
    listen ${PORT} default_server;
    listen [::]:${PORT} default_server;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;
    
    # Logging
    access_log /dev/stdout;
    error_log /dev/stderr warn;
    
    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    # Security
    location ~ /\.(env|git) { deny all; return 404; }
}
EOF

# Remove default nginx config and link ours
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Show the actual config
echo "==> Nginx configuration created:"
cat /etc/nginx/sites-available/default

# Test nginx config
echo "==> Testing nginx configuration..."
if ! nginx -t; then
    echo "ERROR: Nginx configuration test failed"
    exit 1
fi

echo "==> Nginx configuration test passed"

# Set permissions
echo "==> Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear Laravel cache
echo "==> Clearing Laravel cache..."
php artisan config:clear || true
php artisan route:clear || true

# Test Laravel
echo "==> Testing Laravel..."
if ! php artisan --version; then
    echo "ERROR: Laravel not working"
    exit 1
fi

echo "==> Laravel version: $(php artisan --version)"

# Start PHP-FPM in background
echo "==> Starting PHP-FPM..."
php-fpm -D

# Verify PHP-FPM is running
sleep 2
if ! pgrep php-fpm > /dev/null; then
    echo "ERROR: PHP-FPM failed to start"
    exit 1
fi

echo "==> PHP-FPM started successfully"

# Start nginx in foreground
echo "==> Starting nginx on port ${PORT}..."
echo "==> Application should be available at: https://lesgo-api-production.up.railway.app"

exec nginx -g "daemon off;"