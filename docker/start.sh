#!/bin/bash

echo "==> Starting LeSGo API..."
echo "==> PORT=${PORT:-8080}"

# Railway injects PORT — default to 8080
PORT=${PORT:-8080}

# Write nginx config with correct port using sed
sed "s/RAILWAY_PORT/${PORT}/g" /etc/nginx/templates/railway.conf.template > /etc/nginx/sites-available/default

# Remove default nginx site if it exists separately
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

echo "==> Nginx will listen on port ${PORT}"

# Ensure storage & cache dirs are writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear stale cache
php artisan config:clear || true
php artisan route:clear  || true

# Wait for DB
echo "==> Waiting for database..."
for i in $(seq 1 30); do
    php artisan db:show --no-interaction > /dev/null 2>&1 && echo "==> DB ready!" && break
    echo "    Waiting ($i/30)..."
    sleep 2
done

# Migrate
echo "==> Running migrations..."
php artisan migrate --force || { echo "ERROR: Migration failed"; exit 1; }

# Cache
php artisan config:cache || echo "config:cache skipped"
php artisan route:cache  || echo "route:cache skipped"

# Swagger
php artisan swagger:generate || echo "Swagger skipped"

echo "==> Launching supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
