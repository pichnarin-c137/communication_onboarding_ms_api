#!/bin/bash
set -e

echo "[entrypoint] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Install composer deps if volume mount wiped vendor
if [ ! -d "vendor" ]; then
    echo "[entrypoint] Installing composer dependencies..."
    composer install --optimize-autoloader --no-interaction
fi

# Create .env from example if it doesn't exist
if [ ! -f ".env" ]; then
    echo "[entrypoint] Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate --force --quiet
fi

# Patch .env with docker service hostnames
echo "[entrypoint] Patching .env for container networking..."
sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-postgres}|" .env
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST:-redis}|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-coms_dev}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-coms_user}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-coms_pass}|" .env

# Clear cached config so patched values are picked up
php artisan config:clear --quiet 2>/dev/null || true

echo "[entrypoint] Running migrations..."
php artisan migrate --force

# Generate JWT keys only if missing
if [ ! -f "storage/keys/jwt_private.pem" ]; then
    echo "[entrypoint] Generating JWT keys..."
    php artisan jwt:generate-keys
fi

echo "[entrypoint] Ready."
exec "$@"
