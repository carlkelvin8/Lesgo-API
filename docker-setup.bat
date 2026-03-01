@echo off
echo ================================
echo LeSGo API - Docker Setup
echo ================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if errorlevel 1 (
    echo X Docker is not installed. Please install Docker Desktop first.
    echo Visit: https://docs.docker.com/desktop/install/windows-install/
    pause
    exit /b 1
)

REM Check if Docker Compose is installed
docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo X Docker Compose is not installed. Please install Docker Compose first.
    pause
    exit /b 1
)

echo [OK] Docker and Docker Compose are installed
echo.

REM Copy environment file
if not exist .env (
    echo [*] Creating .env file from .env.docker...
    copy .env.docker .env
    echo [OK] .env file created
) else (
    echo [!] .env file already exists, skipping...
)
echo.

REM Stop any running containers
echo [*] Stopping any running containers...
docker-compose down
echo.

REM Build and start containers
echo [*] Building Docker containers...
docker-compose build
echo.

echo [*] Starting Docker containers...
docker-compose up -d
echo.

REM Wait for database to be ready
echo [*] Waiting for database to be ready...
timeout /t 10 /nobreak >nul
echo.

REM Install dependencies
echo [*] Installing Composer dependencies...
docker-compose exec -T app composer install --no-interaction
echo.

REM Generate application key
echo [*] Generating application key...
docker-compose exec -T app php artisan key:generate --force
echo.

REM Run migrations
echo [*] Running database migrations...
docker-compose exec -T app php artisan migrate:fresh --force
echo.

REM Seed database
echo [*] Seeding database...
docker-compose exec -T app php artisan db:seed --force 2>nul || echo [!] No seeders found, skipping...
echo.

REM Clear and cache config
echo [*] Optimizing application...
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
echo.

REM Set permissions
echo [*] Setting permissions...
docker-compose exec -T app chown -R www-data:www-data /var/www/html/storage
docker-compose exec -T app chown -R www-data:www-data /var/www/html/bootstrap/cache
docker-compose exec -T app chmod -R 755 /var/www/html/storage
docker-compose exec -T app chmod -R 755 /var/www/html/bootstrap/cache
echo.

echo ================================
echo [OK] Setup Complete!
echo ================================
echo.
echo [*] API is running at: http://localhost:8000
echo [*] Adminer (DB Manager): http://localhost:8080
echo.
echo [*] Database Credentials:
echo    Server: db
echo    Database: lesgo_db
echo    Username: postgres
echo    Password: secret
echo.
echo [*] Useful Commands:
echo    View logs:        docker-compose logs -f app
echo    Stop containers:  docker-compose down
echo    Restart:          docker-compose restart
echo    Run migrations:   docker-compose exec app php artisan migrate
echo    Run tests:        docker-compose exec app php artisan test
echo    Access shell:     docker-compose exec app bash
echo.
echo [*] Test the API:
echo    curl http://localhost:8000/api/v1/ping
echo.
pause
