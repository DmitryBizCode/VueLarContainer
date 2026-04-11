# Stage 1: Composer dependencies
FROM composer:2.7 AS composer-stage
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Stage 2: PHP-FPM with extensions
# Bookworm instead of Alpine: avoids intermittent apk/gcc "I/O error" / integrity failures
# during `docker compose build` on some Docker Desktop (e.g. Apple Silicon) setups.
FROM php:8.2-fpm-bookworm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    && rm -rf /var/lib/apt/lists/*

# PHP Redis extension (PECL)
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear \
        /usr/local/lib/php/doc/redis /usr/local/lib/php/test/redis

# Copy Composer from composer stage
COPY --from=composer-stage /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and vendor
COPY composer.json composer.lock ./
COPY --from=composer-stage /app/vendor ./vendor

ENV COMPOSER_ALLOW_SUPERUSER=1
# --no-scripts: skip "php artisan package:discover" (needs app files); Laravel will discover at runtime
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
