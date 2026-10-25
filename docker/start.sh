#!/bin/sh
# Start script for Render deployment
# Render sets the $PORT and APP_KEY environment variables

# Use externally configured APP_KEY; fail clearly if missing
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY not set - required for Laravel encryption" >&2
    exit 1
fi

# Set Apache to listen on the Render-assigned port
# Apache default is 80, but Render maps $PORT to the container
if [ -n "$PORT" ]; then
    # Update Apache port if needed
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf 2>/dev/null || true
fi

# Ensure storage and cache directories are writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/storage 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/storage 2>/dev/null || true

# Start Apache
exec apache2-foreground