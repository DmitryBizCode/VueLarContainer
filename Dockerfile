# Stage 1: Composer dependencies
FROM composer:2.7 AS composer-stage
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Stage 2: PHP-FPM with extensions
FROM php:8.2-fpm-alpine AS php-base

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    libpng \
    libpng-dev \
    libjpeg-turbo \
    libjpeg-turbo-dev \
    freetype \
    freetype-dev \
    libonig \
    libonig-dev \
    libxml2 \
    libxml2-dev \
    postgresql-libs \
    postgresql-dev \
    redis \
    redis-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    redis \
    && apk del --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libonig-dev \
    libxml2-dev \
    postgresql-dev \
    redis-dev \
    && rm -rf /var/cache/apk/*

# Copy Composer from composer stage
COPY --from=composer-stage /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
COPY --from=composer-stage /app/vendor ./vendor
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

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
