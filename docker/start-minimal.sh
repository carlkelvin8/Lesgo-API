#!/bin/bash
set -e

echo "==> MINIMAL STARTUP - LeSGo API"
echo "==> PORT: ${PORT:-8080}"

PORT=${PORT:-8080}

# Critical check
if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY not set"
    exit 1
fi

echo "==> APP_KEY: ${APP_KEY:0:15}..."

# Simple nginx config - no template
cat > /etc/nginx/sites-available/default << EOF
server {
    listen ${PORT};
    root /var/www/html/public;
    index index.php;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

# Test nginx config
nginx -t || exit 1

# Permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear cache
php artisan config:clear || true
php artisan route:clear || true

# Test Laravel
php artisan --version || exit 1

# Skip database for now - just start the app
echo "==> Starting services..."

# Start PHP-FPM
php-fpm -D

# Start nginx in foreground
exec nginx -g "daemon off;"