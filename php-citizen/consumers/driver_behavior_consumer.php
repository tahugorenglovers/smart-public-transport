<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\DriverAlert;
use App\Models\Notification;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Behaviors that should trigger an alert + notification
const DANGEROUS_BEHAVIORS = ['aggressive', 'dangerous'];

const EXCHANGE    = 'smarttransit';
const QUEUE       = 'driver.behavior.detected';
const ROUTING_KEY = 'driver.behavior.detected';

function buildAlertMessage(int $busId, string $behavior): string
{
    $label = ucfirst($behavior); // Aggressive / Dangerous
    return "Bus {$busId} terdeteksi mengemudi {$label}";
}

function handleEvent(array $event): void
{
    $busId    = (int) ($event['bus_id'] ?? 0);
    $behavior = strtolower((string) ($event['behavior'] ?? ''));
    $severity = (string) ($event['severity'] ?? 'unknown');

    echo "[Consumer] Received event: bus_id={$busId}, behavior={$behavior}, severity={$severity}\n";

    if (!in_array($behavior, DANGEROUS_BEHAVIORS, true)) {
        echo "[Consumer] Behavior '{$behavior}' is not dangerous, skipping.\n";
        return;
    }

    if ($busId <= 0) {
        echo "[Consumer] Invalid bus_id, skipping.\n";
        return;
    }

    $message = buildAlertMessage($busId, $behavior);

    // 1. Store alert
    $alertModel = new DriverAlert();
    $alert      = $alertModel->create($busId, $severity, $message);
    echo "[Consumer] Alert stored with id={$alert['id']}\n";

    // 2. Broadcast notification to all citizens
    $notifModel = new Notification();
    $count      = $notifModel->broadcastToAll('Safety Alert', $message);
    echo "[Consumer] Notification broadcasted to {$count} users\n";
}

function startConsumer(): void
{
    $host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
    $port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
    $user = $_ENV['RABBITMQ_USER'] ?? 'guest';
    $pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';

    $connection = new AMQPStreamConnection($host, $port, $user, $pass);
    $channel    = $connection->channel();

    // Declare exchange (idempotent, must match publisher's config)
    $channel->exchange_declare(EXCHANGE, 'topic', false, true, false);

    // Declare queue and bind to routing key
    $channel->queue_declare(QUEUE, false, true, false, false);
    $channel->queue_bind(QUEUE, EXCHANGE, ROUTING_KEY);

    $callback = function (AMQPMessage $msg) use ($channel) {
        try {
            $event = json_decode($msg->getBody(), true);

            if (!is_array($event)) {
                throw new \RuntimeException('Invalid JSON payload');
            }

            handleEvent($event);
            $channel->basic_ack($msg->getDeliveryTag());
        } catch (\Throwable $e) {
            echo '[Consumer] Error processing message: ' . $e->getMessage() . "\n";
            // Reject and don't requeue malformed messages
            $channel->basic_reject($msg->getDeliveryTag(), false);
        }
    };

    $channel->basic_qos(0, 1, false); // process one message at a time
    $channel->basic_consume(QUEUE, '', false, false, false, false, $callback);

    echo "[Consumer] Listening on '" . QUEUE . "' (exchange: " . EXCHANGE . ")...\n";

    while ($channel->is_consuming()) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();
}

$maxRetries = 10;
$attempt    = 0;

while ($attempt < $maxRetries) {
    try {
        startConsumer();
        break;
    } catch (\Exception $e) {
        $attempt++;
        echo "[Consumer] Connection failed ({$attempt}/{$maxRetries}): " . $e->getMessage() . "\n";
        sleep(5);
    }
}