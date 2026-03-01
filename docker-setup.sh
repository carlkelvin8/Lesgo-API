#!/bin/bash

echo "================================"
echo "LeSGo API - Docker Setup"
echo "================================"
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    echo "Visit: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    echo "Visit: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✅ Docker and Docker Compose are installed"
echo ""

# Copy environment file
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.docker..."
    cp .env.docker .env
    echo "✅ .env file created"
else
    echo "⚠️  .env file already exists, skipping..."
fi
echo ""

# Stop any running containers
echo "🛑 Stopping any running containers..."
docker-compose down
echo ""

# Build and start containers
echo "🏗️  Building Docker containers..."
docker-compose build
echo ""

echo "🚀 Starting Docker containers..."
docker-compose up -d
echo ""

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10
echo ""

# Install dependencies
echo "📦 Installing Composer dependencies..."
docker-compose exec -T app composer install --no-interaction
echo ""

# Generate application key if needed
echo "🔑 Generating application key..."
docker-compose exec -T app php artisan key:generate --force
echo ""

# Run migrations
echo "🗄️  Running database migrations..."
docker-compose exec -T app php artisan migrate:fresh --force
echo ""

# Seed database (optional)
echo "🌱 Seeding database..."
docker-compose exec -T app php artisan db:seed --force || echo "⚠️  No seeders found, skipping..."
echo ""

# Clear and cache config
echo "🔧 Optimizing application..."
docker-compose exec -T app php artisan config:cache
docker-compose exec -T app php artisan route:cache
echo ""

# Set permissions
echo "🔒 Setting permissions..."
docker-compose exec -T app chown -R www-data:www-data /var/www/html/storage
docker-compose exec -T app chown -R www-data:www-data /var/www/html/bootstrap/cache
docker-compose exec -T app chmod -R 755 /var/www/html/storage
docker-compose exec -T app chmod -R 755 /var/www/html/bootstrap/cache
echo ""

echo "================================"
echo "✅ Setup Complete!"
echo "================================"
echo ""
echo "🌐 API is running at: http://localhost:8000"
echo "📊 Adminer (DB Manager): http://localhost:8080"
echo ""
echo "📝 Database Credentials:"
echo "   Server: db"
echo "   Database: lesgo_db"
echo "   Username: postgres"
echo "   Password: secret"
echo ""
echo "🔧 Useful Commands:"
echo "   View logs:        docker-compose logs -f app"
echo "   Stop containers:  docker-compose down"
echo "   Restart:          docker-compose restart"
echo "   Run migrations:   docker-compose exec app php artisan migrate"
echo "   Run tests:        docker-compose exec app php artisan test"
echo "   Access shell:     docker-compose exec app bash"
echo ""
echo "📖 Test the API:"
echo "   curl http://localhost:8000/api/v1/ping"
echo ""
