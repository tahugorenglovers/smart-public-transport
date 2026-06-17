FROM php:8.2-cli

WORKDIR /app

# System dependencies & PHP extensions
# (no ext-amqp needed: this service talks to RabbitMQ via the pure-PHP
# php-amqplib composer package, not the amqp pecl extension)
RUN apt-get update && apt-get install -y \
        unzip curl libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies first so this layer is cached unless
# composer.json/lock actually change
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application source
COPY . .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# public/index.php is passed as the router script so PHP's built-in
# server dispatches EVERY request (not just literal files) into Slim's
# router. Without this, every route except a couple of edge cases
# returns "No input file specified".
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
