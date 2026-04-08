#!/bin/bash
set -e

echo "==> SIMPLE RAILWAY STARTUP"
echo "==> PORT: ${PORT:-8080}"

PORT=${PORT:-8080}

# Basic validation
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY not set"
    exit 1
fi

echo "==> APP_KEY: OK"

# Create minimal nginx config
echo "==> Creating nginx config for port $PORT"
cat > /etc/nginx/sites-available/default << EOF
server {
    listen $PORT default_server;
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

echo "==> Nginx config created"

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear cache
php artisan config:clear || true

# Start PHP-FPM
echo "==> Starting PHP-FPM"
php-fpm -D

# Start nginx
echo "==> Starting nginx on port $PORT"
nginx -g "daemon off;"