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
    # Update Apache listen port
    if ! sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf 2>/dev/null; then
        echo "ERROR: Failed to update Apache listen port in /etc/apache2/ports.conf" >&2
        exit 1
    fi

    # Keep VirtualHost port in sync with Listen port
    if ! sed -i "s/\*:80/\*:$PORT/" /etc/apache2/sites-enabled/000-default.conf 2>/dev/null; then
        echo "ERROR: Failed to update VirtualHost port in /etc/apache2/sites-enabled/000-default.conf" >&2
        exit 1
    fi

    # Validate that both Listen and VirtualHost use the same port
    if ! grep -q "Listen $PORT" /etc/apache2/ports.conf 2>/dev/null; then
        echo "ERROR: Apache Listen port not set to $PORT" >&2
        exit 1
    fi
    if ! grep -q "\*:$PORT" /etc/apache2/sites-enabled/000-default.conf 2>/dev/null; then
        echo "ERROR: Apache VirtualHost port not set to $PORT" >&2
        exit 1
    fi
    if ! grep -q 'DocumentRoot /var/www/html/public' /etc/apache2/sites-enabled/000-default.conf 2>/dev/null; then
        echo "ERROR: DocumentRoot not set to /var/www/html/public" >&2
        exit 1
    fi

    # Ensure Apache configuration is valid for the updated ports and document root
    if ! apache2ctl configtest >/dev/null 2>&1; then
        echo "ERROR: Apache configuration test failed" >&2
        exit 1
    fi
fi

# Ensure storage and cache directories are writable
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/storage 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/storage 2>/dev/null || true

# Start Apache
exec apache2-foreground