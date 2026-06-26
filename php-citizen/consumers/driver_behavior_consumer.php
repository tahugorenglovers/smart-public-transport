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

const DANGEROUS_BEHAVIORS = ['aggressive', 'dangerous'];

const EXCHANGE             = 'smarttransit';
const QUEUE_DRIVER         = 'driver.behavior.detected';
const ROUTING_KEY_DRIVER   = 'driver.behavior.detected';

function buildDriverMessage(int $busId, string $behavior): string
{
    $label = ucfirst($behavior);
    return "Bus {$busId} terdeteksi mengemudi {$label}";
}

function handleDriverEvent(array $event): void
{
    $busId    = (int) ($event['bus_id'] ?? 0);
    $behavior = strtolower((string) ($event['behavior'] ?? ''));
    $severity = (string) ($event['severity'] ?? 'unknown');

    echo "[Driver Consumer] bus_id={$busId}, behavior={$behavior}, severity={$severity}\n";

    if (!in_array($behavior, DANGEROUS_BEHAVIORS, true)) {
        echo "[Driver Consumer] Behavior '{$behavior}' is safe, skipping.\n";
        return;
    }

    if ($busId <= 0) {
        echo "[Driver Consumer] Invalid bus_id, skipping.\n";
        return;
    }

    $message = buildDriverMessage($busId, $behavior);

    // 1. Store alert
    $alertModel = new DriverAlert();
    $alert      = $alertModel->create($busId, $severity, $message);
    echo "[Driver Consumer] Alert stored with id={$alert['id']}\n";

    // 2. Broadcast notification to all citizens
    $notifModel = new Notification();
    $count      = $notifModel->broadcastToAll('Safety Alert', $message);
    echo "[Driver Consumer] Notification broadcasted to {$count} users\n";
}

function startDriverConsumer(): void
{
    $host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
    $port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
    $user = $_ENV['RABBITMQ_USER'] ?? 'guest';
    $pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';

    $connection = new AMQPStreamConnection($host, $port, $user, $pass);
    $channel    = $connection->channel();

    $channel->exchange_declare(EXCHANGE, 'topic', false, true, false);
    $channel->queue_declare(QUEUE_DRIVER, false, true, false, false);
    $channel->queue_bind(QUEUE_DRIVER, EXCHANGE, ROUTING_KEY_DRIVER);

    $callback = function (AMQPMessage $msg) use ($channel) {
        try {
            $event = json_decode($msg->getBody(), true);
            if (!is_array($event)) {
                throw new \RuntimeException('Invalid JSON payload');
            }
            handleDriverEvent($event);
            $channel->basic_ack($msg->getDeliveryTag());
        } catch (\Throwable $e) {
            echo '[Driver Consumer] Error: ' . $e->getMessage() . "\n";
            $channel->basic_reject($msg->getDeliveryTag(), false);
        }
    };

    $channel->basic_qos(0, 1, false);
    $channel->basic_consume(QUEUE_DRIVER, '', false, false, false, false, $callback);

    echo "[Driver Consumer] Listening on '" . QUEUE_DRIVER . "'...\n";

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
        startDriverConsumer();
        break;
    } catch (\Exception $e) {
        $attempt++;
        echo "[Driver Consumer] Connection failed ({$attempt}/{$maxRetries}): " . $e->getMessage() . "\n";
        sleep(5);
    }
}