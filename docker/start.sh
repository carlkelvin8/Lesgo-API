#!/bin/bash
set -e

echo "==> Starting LeSGo API..."

# Railway injects PORT — default to 80 for local Docker
export PORT=${PORT:-80}

# Substitute $PORT into nginx config
envsubst '${PORT}' < /etc/nginx/templates/railway.conf.template > /etc/nginx/sites-available/default

# Ensure storage & cache dirs are writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear cached config (Railway sets env vars at runtime)
php artisan config:clear
php artisan route:clear

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force

# Cache config & routes for performance
echo "==> Caching config and routes..."
php artisan config:cache || echo "config:cache skipped (non-serializable value)"
php artisan route:cache

# Generate swagger docs
echo "==> Generating Swagger docs..."
php artisan swagger:generate || echo "Swagger generation skipped"

echo "==> Starting supervisor (nginx + php-fpm)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
