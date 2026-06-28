<?php

namespace App\Config;

class RabbitMQ {
    public static function getConfig(): array {
        return [
            'host' => $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
            'port' => (int)($_ENV['RABBITMQ_PORT'] ?? 5672),
            'user' => $_ENV['RABBITMQ_USER'] ?? 'guest',
            'password' => $_ENV['RABBITMQ_PASS'] ?? 'guest',
            'vhost' => $_ENV['RABBITMQ_VHOST'] ?? '/',
            'exchange' => 'smarttransit',
        ];
    }
}