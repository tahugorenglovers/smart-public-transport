FROM php:8.2-apache

# Install system dependencies dan PHP extensions
RUN apt-get update && apt-get install -y \
    librabbitmq-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite (untuk .htaccess)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy semua file project
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Arahkan DocumentRoot ke folder /public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
    /etc/apache2/sites-available/000-default.conf

# Allow .htaccess override
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/sites-available/000-default.conf

# Buat folder logs dan set permission
RUN mkdir -p storage/logs && chmod -R 777 storage/

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 8001

CMD ["apache2-foreground"]