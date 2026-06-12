FROM php:8.2-cli

WORKDIR /app

# Install dependencies
RUN apt-get update && apt-get install -y \
    librabbitmq-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apt-get clean

# Copy semua file
COPY . .

EXPOSE 8002

HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8002/health || exit 1

CMD ["php", "-S", "0.0.0.0:8002", "-t", "public"]