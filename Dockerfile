FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    openssl \
    autoconf \
    build-essential

# Install PHP extensions
# RUN docker-php-ext-install pdo pdo_pgsql mbstring
RUN docker-php-ext-install pdo pdo_mysql mbstring

# Install Xdebug via pecl and enable it
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy Xdebug ini (if exists)
RUN if [ -f docker/php/conf.d/xdebug.ini ]; then \
        cp docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini; \
    fi

# Install dependencies
RUN composer install --optimize-autoloader

# Create keys directory and set permissions
RUN mkdir -p storage/keys && chmod -R 775 storage bootstrap/cache

# Create startup script
RUN echo '#!/bin/bash\n\
set -e\n\
PORT=${PORT:-8000}\n\
# Set umask for new files\n\
umask 0002\n\
# Fix storage & bootstrap/cache ownership and permissions\n\
echo "Fixing storage ownership and permissions..."\n\
chown -R www-data:www-data storage bootstrap/cache\n\
find storage -type d -exec chmod 775 {} \;\n\
find storage -type f -exec chmod 664 {} \;\n\
chmod -R 775 bootstrap/cache\n\
# Ensure storage link exists\n\
php artisan storage:link 2>/dev/null || true\n\
echo "Running migrations..."\n\
php artisan migrate --force\n\
echo "Seeding database..."\n\
php artisan db:seed --force\n\
echo "Generating JWT keys..."\n\
php artisan jwt:generate-keys\n\
echo "Starting server on port $PORT..."\n\
php artisan serve --host=0.0.0.0 --port=$PORT\n' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 8000

CMD ["/usr/local/bin/start.sh"]
