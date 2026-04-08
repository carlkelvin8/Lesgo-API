#!/bin/bash
echo "==> Starting LeSGo API with artisan serve"
echo "==> PORT: ${PORT:-8000}"
echo "==> Host: 0.0.0.0"

# Run migrations if needed
php artisan migrate --force || echo "Migration skipped"

# Start Laravel development server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000} --no-reload