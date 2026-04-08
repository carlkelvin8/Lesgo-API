FROM php:8.2-fpm

# Railway deployment fix - v2.0
LABEL version="2.0" description="Railway deployment with direct nginx config"

WORKDIR /var/www/html

# System dependencies (removed supervisor)
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libpq-dev libzip-dev zip unzip nginx gettext-base \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy runtime configs AFTER app copy so they aren't overwritten
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Copy Railway-specific startup script (NO supervisor)
COPY docker/start-railway.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Remove supervisor completely - we don't need it
# RUN apt-get remove -y supervisor

# Verify the startup script is there
RUN ls -la /usr/local/bin/start.sh && head -5 /usr/local/bin/start.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 8080

# Use our Railway startup script directly
ENTRYPOINT ["/usr/local/bin/start.sh"]
