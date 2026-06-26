<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Notification;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

const ENV_EXCHANGE           = 'smarttransit';
const QUEUE_TEMPERATURE      = 'bus.temperature.alert';
const ROUTING_KEY_TEMPERATURE= 'bus.temperature.alert';
const QUEUE_OVERCROWDED      = 'bus.overcrowded';
const ROUTING_KEY_OVERCROWDED= 'bus.overcrowded';

function buildTemperatureMessage(int $busId, float $temperature, string $status): string
{
    return "Bus {$busId} mengalami suhu tinggi ({$temperature}°C) - Status: {$status}";
}

function buildOvercrowdedMessage(int $busId, int $passengerCount, string $status): string
{
    return "Bus {$busId} melebihi kapasitas ({$passengerCount} penumpang) - Status: {$status}";
}

function handleTemperatureEvent(array $event): void
{
    $busId       = (int) ($event['bus_id'] ?? 0);
    $temperature = (float) ($event['temperature'] ?? 0);
    $status      = (string) ($event['status'] ?? 'unknown');

    echo "[Env Consumer] Temperature alert: bus_id={$busId}, temp={$temperature}, status={$status}\n";

    if ($busId <= 0) {
        echo "[Env Consumer] Invalid bus_id, skipping.\n";
        return;
    }

    $message    = buildTemperatureMessage($busId, $temperature, $status);
    $notifModel = new Notification();
    $count      = $notifModel->broadcastToAll('Temperature Alert', $message);

    echo "[Env Consumer] Notification broadcasted to {$count} users\n";
}

function handleOvercrowdedEvent(array $event): void
{
    $busId          = (int) ($event['bus_id'] ?? 0);
    $passengerCount = (int) ($event['passenger_count'] ?? 0);
    $status         = (string) ($event['status'] ?? 'unknown');

    echo "[Env Consumer] Overcrowded alert: bus_id={$busId}, passengers={$passengerCount}, status={$status}\n";

    if ($busId <= 0) {
        echo "[Env Consumer] Invalid bus_id, skipping.\n";
        return;
    }

    $message    = buildOvercrowdedMessage($busId, $passengerCount, $status);
    $notifModel = new Notification();
    $count      = $notifModel->broadcastToAll('Bus Overcrowded', $message);

    echo "[Env Consumer] Notification broadcasted to {$count} users\n";
}

function startEnvironmentConsumer(): void
{
    $host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
    $port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
    $user = $_ENV['RABBITMQ_USER'] ?? 'guest';
    $pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';

    $connection = new AMQPStreamConnection($host, $port, $user, $pass);
    $channel    = $connection->channel();

    // Declare exchange
    $channel->exchange_declare(ENV_EXCHANGE, 'topic', false, true, false);

    // Declare and bind temperature queue
    $channel->queue_declare(QUEUE_TEMPERATURE, false, true, false, false);
    $channel->queue_bind(QUEUE_TEMPERATURE, ENV_EXCHANGE, ROUTING_KEY_TEMPERATURE);

    // Declare and bind overcrowded queue
    $channel->queue_declare(QUEUE_OVERCROWDED, false, true, false, false);
    $channel->queue_bind(QUEUE_OVERCROWDED, ENV_EXCHANGE, ROUTING_KEY_OVERCROWDED);

    // Callback untuk temperature
    $temperatureCallback = function (AMQPMessage $msg) use ($channel) {
        try {
            $event = json_decode($msg->getBody(), true);
            if (!is_array($event)) {
                throw new \RuntimeException('Invalid JSON payload');
            }
            handleTemperatureEvent($event);
            $channel->basic_ack($msg->getDeliveryTag());
        } catch (\Throwable $e) {
            echo '[Env Consumer] Temperature error: ' . $e->getMessage() . "\n";
            $channel->basic_reject($msg->getDeliveryTag(), false);
        }
    };

    // Callback untuk overcrowded
    $overcrowdedCallback = function (AMQPMessage $msg) use ($channel) {
        try {
            $event = json_decode($msg->getBody(), true);
            if (!is_array($event)) {
                throw new \RuntimeException('Invalid JSON payload');
            }
            handleOvercrowdedEvent($event);
            $channel->basic_ack($msg->getDeliveryTag());
        } catch (\Throwable $e) {
            echo '[Env Consumer] Overcrowded error: ' . $e->getMessage() . "\n";
            $channel->basic_reject($msg->getDeliveryTag(), false);
        }
    };

    $channel->basic_qos(0, 1, false);
    $channel->basic_consume(QUEUE_TEMPERATURE, '', false, false, false, false, $temperatureCallback);
    $channel->basic_consume(QUEUE_OVERCROWDED, '', false, false, false, false, $overcrowdedCallback);

    echo "[Env Consumer] Listening on '" . QUEUE_TEMPERATURE . "' and '" . QUEUE_OVERCROWDED . "'...\n";

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
        startEnvironmentConsumer();
        break;
    } catch (\Exception $e) {
        $attempt++;
        echo "[Env Consumer] Connection failed ({$attempt}/{$maxRetries}): " . $e->getMessage() . "\n";
        sleep(5);
    }
}