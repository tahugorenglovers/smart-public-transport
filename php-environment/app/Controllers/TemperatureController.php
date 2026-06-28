<?php

require_once __DIR__ . '/../Models/TemperatureReading.php';
require_once __DIR__ . '/../Models/Alert.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';
require_once __DIR__ . '/../Validators/SensorValidator.php';

class TemperatureController {
    private TemperatureReading $model;
    private Alert $alertModel;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model      = new TemperatureReading();
        $this->alertModel = new Alert();
        $this->publisher  = new RabbitMQPublisher();
    }

    public function store(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        $errors = SensorValidator::validateTemperature($body);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'status'    => 'error',
                'code'      => 422,
                'message'   => 'Validation failed',
                'errors'    => $errors,
                'service'   => 'environment-service',
                'timestamp' => date('c')
            ]);
            return;
        }

        $busId       = (int)$body['bus_id'];
        $temperature = (float)$body['temperature'];

        $record = $this->model->create($busId, $temperature);
        $status = $this->model->getStatus($temperature);

        // Alert rules from spec: temp > 32 → overheating
        if ($status === 'BERBAHAYA' || $status === 'PANAS') {
            $severity    = $status === 'BERBAHAYA' ? 'critical' : 'high';
            $description = "Bus {$busId}: suhu {$temperature}°C — {$status}";

            // Store in env_alerts so GET /api/environment/alerts shows it
            $this->alertModel->create($busId, 'overheating', $severity, $description);

            // Publish to RabbitMQ
            $this->publisher->publish('bus.temperature.alert', [
                'bus_id'      => $busId,
                'temperature' => $temperature,
                'status'      => $status,
                'severity'    => $severity,
                'description' => $description,
                'timestamp'   => date('c'),
            ]);
        }

        http_response_code(201);
        echo json_encode([
            'status'    => 'success',
            'code'      => 201,
            'data'      => array_merge($record, ['temperature_status' => $status]),
            'message'   => 'Temperature reading recorded',
            'service'   => 'environment-service',
            'timestamp' => date('c'),
        ]);
    }
}
