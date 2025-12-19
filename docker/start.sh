#!/bin/bash

echo "Starting Laravel application..."

# Set default port if not provided
export PORT=${PORT:-8080}
echo "Using PORT: $PORT"

# Update nginx config with actual port
sed -i "s/PORT_PLACEHOLDER/$PORT/g" /etc/nginx/sites-available/default

# Create storage directories if they don't exist
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

# Set permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force || true
fi

# Clear caches (don't fail on error)
echo "Clearing caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Run migrations (don't fail if DB not ready)
echo "Running migrations..."
php artisan migrate --force 2>/dev/null || echo "Migration skipped or failed"

# Create supervisor log directory
mkdir -p /var/log/supervisor

echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
