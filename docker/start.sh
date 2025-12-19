#!/bin/bash
set -e

echo "Starting Laravel application..."

# Create storage directories if they don't exist
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Clear and cache config
php artisan config:clear
php artisan config:cache

# Run migrations
php artisan migrate --force

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Create supervisor log directory
mkdir -p /var/log/supervisor

echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
