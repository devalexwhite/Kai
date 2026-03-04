FROM php:8.4-fpm-alpine

# Install PDO SQLite extension (sqlite-dev provides the headers needed on Alpine)
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

# Configure PHP sessions to use a writable path
RUN echo "session.save_path = /tmp/php-sessions" \
    > /usr/local/etc/php/conf.d/sessions.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer manifests first for layer caching (lock file pinned to reproducible builds)
COPY composer.json composer.lock* ./

# Install PHP dependencies (no dev, optimized autoloader)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

# Copy application source (vendor/ and database/ excluded by .dockerignore)
COPY . .

# Create runtime directories and set permissions
# /tmp/php-sessions is mounted as a named volume at runtime (see docker-compose.yml)
RUN mkdir -p database /tmp/php-sessions \
    && chown -R www-data:www-data /var/www/html /tmp/php-sessions

USER www-data
