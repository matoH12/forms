FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    nodejs \
    npm \
    default-mysql-client \
    libfcgi-bin \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (including ftp for flysystem-ftp)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache ftp

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Configure PHP-FPM for better concurrency
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create system user with same UID/GID as host (for volume permissions)
RUN groupadd -g 1000 www && \
    useradd -u 1000 -ms /bin/bash -g www www

# Create necessary directories
RUN mkdir -p /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/www/html/public-src

# Copy composer files first (for better caching)
COPY --chown=www:www ./src/composer.json ./src/composer.lock /var/www/html/

# Install PHP dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy package.json for npm
COPY --chown=www:www ./src/package.json ./src/package-lock.json* /var/www/html/

# Install ALL npm dependencies (including dev for build tools like vite)
RUN npm ci 2>/dev/null || npm install

# Copy all application files
COPY --chown=www:www ./src /var/www/html

# Clear any cached package discovery from development (IMPORTANT!)
RUN rm -f /var/www/html/bootstrap/cache/packages.php \
    /var/www/html/bootstrap/cache/services.php \
    /var/www/html/bootstrap/cache/config.php \
    /var/www/html/bootstrap/cache/routes-v7.php

# Build frontend assets first (requires vite from dev dependencies)
RUN npm run build

# Now reinstall composer without dev dependencies and regenerate autoloader
# This ensures dev packages like collision are not included
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Backup public files (will be used if volume is empty)
RUN cp -r /var/www/html/public/* /var/www/html/public-src/

# Copy entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create healthcheck script for PHP-FPM
RUN printf '#!/bin/bash\nSCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong && exit 0 || exit 1\n' > /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Set permissions for all directories that will be volumes
RUN chown -R www:www /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/public \
    && chmod -R 775 /var/www/html/public-src

# Clean up
RUN rm -rf /var/www/html/node_modules \
    && npm cache clean --force

# Change current user to www
USER www

EXPOSE 9000

# Use entrypoint for initialization
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
