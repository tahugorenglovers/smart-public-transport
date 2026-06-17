FROM php:8.2-apache

# System dependencies & PHP extensions
RUN apt-get update && apt-get install -y \
        libzip-dev \
        unzip \
        curl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        sockets \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite (needed for the .htaccess front-controller)
RUN a2enmod rewrite

# Make Apache listen on 8001 so the in-container port matches every
# other service's convention (and the TRAFFIC_SERVICE_URL used by the
# gateway to reach this container over the Docker network)
RUN sed -i 's/^Listen 80$/Listen 8001/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8001>/' /etc/apache2/sites-available/000-default.conf

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached unless
# composer.json/lock actually change
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application source
COPY . .

# Serve from /public, and allow its .htaccess to override Apache config
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && { \
        echo '<Directory /var/www/html/public>'; \
        echo '    Options Indexes FollowSymLinks'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } >> /etc/apache2/sites-available/000-default.conf

RUN mkdir -p storage/logs && chmod -R 777 storage/

EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8001/health || exit 1

CMD ["apache2-foreground"]
