#!/bin/bash

echo "==> Starting LeSGo API..."
echo "==> PORT=${PORT:-8080}"

PORT=${PORT:-8080}

# Write nginx config with correct port
sed "s/RAILWAY_PORT/${PORT}/g" /etc/nginx/templates/railway.conf.template > /etc/nginx/sites-available/default
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
echo "==> Nginx will listen on port ${PORT}"
echo "==> Nginx config:"
grep "listen" /etc/nginx/sites-available/default

# Permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear stale cache
php artisan config:clear || true
php artisan route:clear  || true

# Wait for DB using TCP check (faster and more reliable than artisan db:show)
echo "==> Waiting for database..."
DB_HOST_VAL="${DB_HOST:-localhost}"
DB_PORT_VAL="${DB_PORT:-5432}"
for i in $(seq 1 30); do
    if (echo > /dev/tcp/${DB_HOST_VAL}/${DB_PORT_VAL}) 2>/dev/null; then
        echo "==> DB ready!"
        break
    fi
    echo "    Waiting ($i/30)..."
    sleep 2
done

# Migrate
echo "==> Running migrations..."
php artisan migrate --force || echo "WARNING: Migration had issues, continuing..."

# Cache
php artisan config:cache || echo "config:cache skipped"
php artisan route:cache  || echo "route:cache skipped"

# Swagger
php artisan swagger:generate || echo "Swagger skipped"

echo "==> Launching supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
