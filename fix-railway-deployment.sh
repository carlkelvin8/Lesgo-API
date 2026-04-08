#!/bin/bash

echo "=== Railway Deployment Fix Script ==="
echo "This script will help diagnose and fix common Railway deployment issues"
echo ""

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    echo "ERROR: This doesn't appear to be a Laravel project (no artisan file found)"
    exit 1
fi

echo "✓ Laravel project detected"

# Check critical files
echo ""
echo "=== Checking critical files ==="

if [ ! -f "Dockerfile" ]; then
    echo "✗ Missing Dockerfile"
    exit 1
else
    echo "✓ Dockerfile found"
fi

if [ ! -f "docker/start.sh" ]; then
    echo "✗ Missing docker/start.sh"
    exit 1
else
    echo "✓ Startup script found"
fi

if [ ! -f "docker/nginx/railway.conf.template" ]; then
    echo "✗ Missing nginx configuration"
    exit 1
else
    echo "✓ Nginx configuration found"
fi

# Check environment file
echo ""
echo "=== Checking environment configuration ==="

if [ ! -f ".env" ]; then
    echo "! No .env file found (this is normal for Railway)"
else
    echo "✓ Local .env file found"
fi

# Test local Laravel installation
echo ""
echo "=== Testing Laravel installation ==="

if ! php artisan --version > /dev/null 2>&1; then
    echo "✗ Laravel not working locally"
    echo "  Run: composer install"
    exit 1
else
    echo "✓ Laravel working locally"
fi

# Check composer dependencies
echo ""
echo "=== Checking dependencies ==="

if [ ! -d "vendor" ]; then
    echo "! Vendor directory missing - running composer install"
    composer install --no-dev --optimize-autoloader
else
    echo "✓ Vendor directory exists"
fi

# Generate fresh app key for Railway
echo ""
echo "=== Generating fresh APP_KEY ==="
NEW_APP_KEY=$(php artisan key:generate --show)
echo "New APP_KEY: $NEW_APP_KEY"
echo ""
echo "IMPORTANT: Copy this key and set it in Railway:"
echo "Railway → Your Project → Variables → APP_KEY = $NEW_APP_KEY"

echo ""
echo "=== Railway Environment Variables Checklist ==="
echo "Make sure these are set in Railway → Variables:"
echo ""
echo "CRITICAL:"
echo "  APP_KEY=$NEW_APP_KEY"
echo "  APP_URL=https://lesgo-api-production.up.railway.app"
echo "  APP_ENV=production"
echo "  APP_DEBUG=false"
echo ""
echo "DATABASE (should auto-populate from PostgreSQL plugin):"
echo "  DB_CONNECTION=pgsql"
echo "  DB_HOST=\${{Postgres.RAILWAY_PRIVATE_DOMAIN}}"
echo "  DB_PORT=5432"
echo "  DB_DATABASE=\${{Postgres.POSTGRES_DB}}"
echo "  DB_USERNAME=\${{Postgres.POSTGRES_USER}}"
echo "  DB_PASSWORD=\${{Postgres.POSTGRES_PASSWORD}}"
echo ""
echo "REDIS (should auto-populate from Redis plugin):"
echo "  REDIS_CLIENT=predis"
echo "  REDIS_HOST=\${{Redis.REDISHOST}}"
echo "  REDIS_PORT=\${{Redis.REDISPORT}}"
echo "  REDIS_PASSWORD=\${{Redis.REDISPASSWORD}}"
echo ""
echo "ADDITIONAL:"
echo "  CACHE_STORE=redis"
echo "  QUEUE_CONNECTION=redis"
echo "  SESSION_DRIVER=redis"
echo "  LOG_CHANNEL=stderr"
echo "  LOG_LEVEL=error"
echo ""
echo "=== Next Steps ==="
echo "1. Set the APP_KEY in Railway (shown above)"
echo "2. Verify PostgreSQL and Redis plugins are added"
echo "3. Check Railway deployment logs for specific errors"
echo "4. Test: https://lesgo-api-production.up.railway.app/api/v1/ping"
echo ""
echo "If still failing, check Railway logs for specific error messages."