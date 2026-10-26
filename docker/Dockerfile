FROM php:8.2-apache

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    redis \
    2>/dev/null || true

# Install PHP extensions required by Laravel
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    gd \
    zip \
    exif \
    pcntl \
    bcmath \
    2>/dev/null || true

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the application code
COPY . /var/www/html

# Copy the Docker startup script into the image and make it executable.
# Source must use the repository-root-relative path `docker/start.sh`
# because the Docker build context is the repository root, not `docker/`.
COPY docker/start.sh /docker/start.sh
RUN chmod +x /docker/start.sh

# Install Composer dependencies (without dev for production)
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Set permissions for Laravel storage and cache
# Create directories first so chown does not fail on missing paths
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache && \
    if [ -d /var/www/html/public/storage ]; then \
        chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/storage; \
    else \
        chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache; \
    fi

# Configure Apache document root to Laravel public directory
COPY docker/000-default.conf /etc/apache2/sites-enabled/000-default.conf
RUN apache2ctl configtest && \
    grep -q 'DocumentRoot /var/www/html/public' /etc/apache2/sites-enabled/000-default.conf || { echo "ERROR: DocumentRoot not set correctly"; exit 1; }

# Expose port 80 (Render will map via PORT env var)
EXPOSE 80

# Start Apache using the start script which handles PORT env variable
CMD ["/docker/start.sh"]