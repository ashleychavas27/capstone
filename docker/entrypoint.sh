#!/bin/bash
set -e

# 1. Dynamically configure Apache port from Render's $PORT env var (default: 80)
PORT="${PORT:-80}"
export PORT

# Adjust Apache configuration to listen on the dynamic port assigned by Render
sed -i "s/Listen .*/Listen ${PORT}/g" /etc/apache2/ports.conf 2>/dev/null || echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost .*/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
echo "export PORT=${PORT}" >> /etc/apache2/envvars

echo "==> Application booting on port ${PORT}..."

# 2. Ensure permissions on Laravel storage and cache directories
mkdir -p /var/www/html/storage/framework/{sessions,views,cache} /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# 3. Production cache optimizations (if APP_KEY is set)
if [ -n "$APP_KEY" ]; then
    echo "==> Caching configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
else
    echo "==> Notice: APP_KEY is not set. Skipping cache optimizations. Remember to set APP_KEY in Render."
fi

# 4. Run database migrations if DB is configured
if [ -n "$DB_HOST" ]; then
    echo "==> Checking database connection and running migrations..."
    php artisan migrate --force || echo "==> Notice: Migration skipped or already up to date."
fi

# 5. Start Apache in foreground
echo "==> Starting web server..."
exec apache2-foreground
