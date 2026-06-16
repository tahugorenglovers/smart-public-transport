<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private $connection = null;
    private $channel = null;

    private function connect(): void {
        if ($this->connection !== null) return;

        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USER') ?: 'guest';
        $pass = getenv('RABBITMQ_PASS') ?: 'guest';

        try {
            $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
            $this->channel = $this->connection->channel();
            $this->channel->exchange_declare('city.events', 'topic', false, true, false);
        } catch (Exception $e) {
            $this->connection = null;
            $this->channel = null;
        }
    }

    public function publish(string $routingKey, array $payload): bool {
        $this->connect();

        if ($this->channel === null) return false;

        try {
            $msg = new AMQPMessage(
                json_encode($payload),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );
            $this->channel->basic_publish($msg, 'city.events', $routingKey);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function __destruct() {
        try {
            if ($this->channel) $this->channel->close();
            if ($this->connection) $this->connection->close();
        } catch (Exception $e) {}
    }
}