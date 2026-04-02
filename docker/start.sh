#!/bin/bash

echo "==> Starting LeSGo API..."

# Railway injects PORT — default to 8080 if not set
export PORT=${PORT:-8080}
echo "==> Using PORT=$PORT"

# Substitute ONLY ${PORT} — leave all nginx $variables intact
envsubst '${PORT}' < /etc/nginx/templates/railway.conf.template > /etc/nginx/sites-available/default

echo "==> Nginx config written for port $PORT"

# Ensure storage & cache dirs are writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear any stale cached config (Railway sets env vars at runtime)
php artisan config:clear  || true
php artisan route:clear   || true
php artisan cache:clear   || true

# Wait for DB to be ready (Railway cold-starts DB separately)
echo "==> Waiting for database..."
for i in $(seq 1 30); do
    php artisan db:show --no-interaction > /dev/null 2>&1 && echo "==> DB ready!" && break
    echo "    DB not ready yet ($i/30), retrying in 2s..."
    sleep 2
done

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force || { echo "ERROR: Migration failed"; exit 1; }

# Cache config & routes for performance
echo "==> Caching config and routes..."
php artisan config:cache || echo "config:cache skipped"
php artisan route:cache  || echo "route:cache skipped"

# Generate swagger docs
echo "==> Generating Swagger docs..."
php artisan swagger:generate || echo "Swagger generation skipped"

echo "==> Starting supervisor (nginx + php-fpm)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
