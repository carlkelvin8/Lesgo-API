#!/bin/bash
set -e  # Exit on any error

echo "==> Starting LeSGo API..."
echo "==> PORT=${PORT:-8080}"
echo "==> PHP Version: $(php --version | head -n1)"
echo "==> Laravel Version: $(php artisan --version 2>/dev/null || echo 'Laravel check failed')"

PORT=${PORT:-8080}

# Validate critical environment variables
echo "==> Environment check:"
echo "    APP_KEY: ${APP_KEY:0:20}..."
echo "    DB_HOST: ${DB_HOST}"
echo "    DB_DATABASE: ${DB_DATABASE}"
echo "    REDIS_HOST: ${REDIS_HOST}"
echo "    APP_URL: ${APP_URL}"

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set!"
    exit 1
fi

if [ -z "$DB_HOST" ]; then
    echo "ERROR: DB_HOST is not set!"
    exit 1
fi

# Write nginx config with correct port
echo "==> Configuring nginx for port ${PORT}"
sed "s/RAILWAY_PORT/${PORT}/g" /etc/nginx/templates/railway.conf.template > /etc/nginx/sites-available/default
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

echo "==> Nginx configuration:"
echo "    Port: ${PORT}"
echo "    Config file contents:"
grep -n "listen\|server_name\|root" /etc/nginx/sites-available/default || echo "    Could not read config"

# Test nginx config
if ! nginx -t; then
    echo "ERROR: Nginx configuration is invalid"
    cat /etc/nginx/sites-available/default
    exit 1
fi

# Permissions
echo "==> Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear stale cache
echo "==> Clearing cache..."
php artisan config:clear || true
php artisan route:clear  || true

# Test basic Laravel functionality
echo "==> Testing Laravel..."
if ! php artisan route:list > /dev/null 2>&1; then
    echo "ERROR: Laravel routes not loading"
    php artisan route:list || echo "Route list failed"
    exit 1
fi

# Wait for database - simplified approach
echo "==> Waiting for database..."
for i in $(seq 1 10); do
    if php artisan db:show > /dev/null 2>&1; then
        echo "==> Database connected!"
        break
    fi
    echo "    Waiting for DB ($i/10)..."
    sleep 3
done

# Run migrations
echo "==> Running migrations..."
if ! php artisan migrate --force; then
    echo "ERROR: Migration failed!"
    echo "==> Database status:"
    php artisan db:show || echo "Cannot connect to database"
    exit 1
fi

# Cache configuration
echo "==> Caching configuration..."
php artisan config:cache || echo "WARNING: config:cache failed"
php artisan route:cache  || echo "WARNING: route:cache failed"

# Generate API docs
echo "==> Generating API documentation..."
php artisan swagger:generate || echo "WARNING: Swagger generation failed"

# Final test
echo "==> Final application test..."
if ! php artisan route:list --path=api/v1/ping > /dev/null 2>&1; then
    echo "ERROR: Ping route not available"
    exit 1
fi

echo "==> Application ready! Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf