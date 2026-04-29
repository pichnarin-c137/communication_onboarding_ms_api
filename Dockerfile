#  BASE STAGE 
FROM php:8.4-fpm-alpine AS base

WORKDIR /var/www/html

# System dependencies
RUN apk add --no-cache \
    git curl zip unzip bash \
    libpq-dev oniguruma-dev libxml2-dev \
    linux-headers \
    tesseract-ocr tesseract-ocr-data-eng

# PHP extensions
RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql \
    mbstring xml bcmath pcntl

# Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

#  DEVELOPMENT STAGE 
# Includes Xdebug and copies code for local development
FROM base AS development
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

# No-op copy (code is usually mounted via volumes in dev)
COPY . .

#  PRODUCTION STAGE 
# Optimized for size and security
FROM base AS production

# Set production environment
ENV APP_ENV=production
ENV APP_DEBUG=false

# Copy application source code
COPY . .

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Permissions cleanup
RUN mkdir -p storage/keys \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Final setup
EXPOSE 9000
CMD ["php-fpm"]
