<?php

declare(strict_types=1);

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $exchange = 'city.events';

    public function __construct()
    {
        $this->host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
        $this->port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
        $this->user = $_ENV['RABBITMQ_USER'] ?? 'guest';
        $this->pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';
    }

    /**
     * Publish a message to the topic exchange.
     * @param string 
     * @param array  
     */
    public function publish(string $routingKey, array $payload): void
    {
        try {
            $connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->pass
            );

            $channel = $connection->channel();

            // Declare exchange
            $channel->exchange_declare(
                $this->exchange,
                'topic',
                false, 
                true,  
                false 
            );

            $body = json_encode(array_merge($payload, [
                'timestamp' => date('c'),
            ]));

            $msg = new AMQPMessage($body, [
                'content_type'  => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $channel->basic_publish($msg, $this->exchange, $routingKey);

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            error_log("[RabbitMQ] Publish failed ({$routingKey}): " . $e->getMessage());
        }
    }
}
