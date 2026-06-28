FROM php:8.2-cli

WORKDIR /app

# System dependencies & PHP extensions
# (no ext-amqp needed: this service talks to RabbitMQ via the pure-PHP
# php-amqplib composer package, not the amqp pecl extension)
RUN apt-get update && apt-get install -y \
        unzip curl libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip sockets \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies first so this layer is cached unless
# composer.json/lock actually change
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application source (this includes consumer.php, which the
# `environment-consumer` service in docker-compose.yml runs instead
# of the HTTP server below, to drain the RabbitMQ anomaly queue)
COPY . .

EXPOSE 8002

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8002/health || exit 1

# public/index.php doubles as the router script so the built-in
# server dispatches every request, not just literal files.
CMD ["php", "-S", "0.0.0.0:8002", "-t", "public", "public/index.php"]
