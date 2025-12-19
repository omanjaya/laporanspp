#!/bin/bash

echo "Starting Laravel application..."

# Set default port if not provided
export PORT=${PORT:-8080}
echo "Using PORT: $PORT"

# Update nginx config with actual port
sed -i "s/PORT_PLACEHOLDER/$PORT/g" /etc/nginx/sites-available/default

# Create .env file from environment variables
echo "Creating .env file..."
cat > .env << EOF
APP_NAME=${APP_NAME:-LaporanSPP}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

DB_CONNECTION=${DB_CONNECTION:-pgsql}
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-laravel}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-}

SESSION_DRIVER=${SESSION_DRIVER:-file}
CACHE_STORE=${CACHE_STORE:-file}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}

LOG_CHANNEL=stack
LOG_LEVEL=error
EOF

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
