<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Models/Alert.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQConsumer {
    private AMQPStreamConnection $connection;
    private $channel;
    private Alert $alertModel;

    public function __construct() {
        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USER') ?: 'guest';
        $pass = getenv('RABBITMQ_PASS') ?: 'guest';

        $this->connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $this->channel = $this->connection->channel();
        $this->alertModel = new Alert();

        $this->channel->exchange_declare('city.events', 'topic', false, true, false);
        $this->channel->queue_declare('env.anomaly.alert', false, true, false, false);
        $this->channel->queue_bind('env.anomaly.alert', 'city.events', 'anomaly.alert');
    }

    public function listen(): void {
        echo "Environment Service - Listening for anomaly.alert events...\n";

        $callback = function (AMQPMessage $msg) {
            $data = json_decode($msg->getBody(), true);

            $busId = (int)($data['bus_id'] ?? 0);
            $alertType = $data['anomaly_type'] ?? 'anomaly_detected';
            $severity = $data['severity'] ?? 'Peringatan';
            $description = $data['description'] ?? 'Anomaly detected by ML service';

            if ($busId > 0) {
                $this->alertModel->create($busId, $alertType, $severity, $description);
                echo "[ALERT SAVED] bus_id={$busId}, type={$alertType}, severity={$severity}\n";
            }

            $msg->ack();
        };

        $this->channel->basic_consume('env.anomaly.alert', '', false, false, false, false, $callback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function __destruct() {
        $this->channel->close();
        $this->connection->close();
    }
}