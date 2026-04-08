FROM php:8.2-fpm

# Railway deployment - SIMPLE approach v4.0 - FORCE REBUILD
LABEL version="4.0" description="Railway deployment - simple startup"

WORKDIR /var/www/html

# Essential packages only - NO supervisor
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libpq-dev libzip-dev zip unzip nginx \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy PHP config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Copy simple startup script
COPY docker/start-simple.sh /start-simple.sh
RUN chmod +x /start-simple.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 8080

# Use simple startup script
CMD ["/start-simple.sh"]