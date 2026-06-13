<?php

namespace App\Services;

use App\Config\RabbitMQ;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;

class RabbitMQService {
    private static ?AMQPStreamConnection $connection = null;

    private static function getConnection(): AMQPStreamConnection {
        if (self::$connection === null || !self::$connection->isConnected()) {
            $cfg = RabbitMQ::getConfig();
            self::$connection = new AMQPStreamConnection(
                $cfg['host'],
                $cfg['port'],
                $cfg['user'],
                $cfg['password'],
                $cfg['vhost']
            );
        }
        return self::$connection;
    }

    public static function publish(string $routingKey, array $payload): bool {
        try {
            $cfg = RabbitMQ::getConfig();
            $channel = self::getConnection()->channel();

            $channel->exchange_declare(
                $cfg['exchange'],   // nama exchange: smarttransit
                'topic',            // tipe exchange
                false,              // passive
                true,               // durable - bertahan meski RabbitMQ restart
                false               // auto-delete
            );

            // Buat message
            $msg = new AMQPMessage(
                json_encode($payload),
                [
                    'content_type'  => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]
            );

            // Publish
            $channel->basic_publish($msg, $cfg['exchange'], $routingKey);
            $channel->close();

            return true;

        } catch (Exception $e) {
            error_log('[RabbitMQ] Publish failed: ' . $e->getMessage());
            return false;
        }
    }
}