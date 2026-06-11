FROM php:8.2-cli

WORKDIR /app

# Install system deps & PHP extensions
RUN apt-get update && apt-get install -y \
    unzip curl libzip-dev librabbitmq-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
