<?php

require_once __DIR__ . '/../Models/AirQualityReading.php';
require_once __DIR__ . '/../Models/Alert.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';
require_once __DIR__ . '/../Validators/SensorValidator.php';

class AirQualityController {
    private AirQualityReading $model;
    private Alert $alertModel;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model      = new AirQualityReading();
        $this->alertModel = new Alert();
        $this->publisher  = new RabbitMQPublisher();
    }

    public function store(): void {
        $body   = json_decode(file_get_contents('php://input'), true);
        $errors = SensorValidator::validateAirQuality($body);

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'status'    => 'error',
                'code'      => 422,
                'message'   => 'Validation failed',
                'errors'    => $errors,
                'service'   => 'environment-service',
                'timestamp' => date('c'),
            ]);
            return;
        }

        $busId  = (int)$body['bus_id'];
        $co2Ppm = (int)$body['co2_ppm'];

        $record = $this->model->create($busId, $co2Ppm);
        $status = $this->model->getStatus($co2Ppm);

        // Alert rule from spec: CO2 > 1000 → poor air quality
        if ($status === 'BURUK' || $status === 'BERBAHAYA') {
            $severity    = $status === 'BERBAHAYA' ? 'critical' : 'high';
            $description = "Bus {$busId}: CO2 {$co2Ppm} ppm — kualitas udara {$status}";

            // Store in env_alerts so GET /api/environment/alerts shows it
            $this->alertModel->create($busId, 'poor_air_quality', $severity, $description);

            // Publish to RabbitMQ so downstream consumers can react
            $this->publisher->publish('bus.anomaly.detected', [
                'bus_id'      => $busId,
                'type'        => 'poor_air_quality',
                'co2_ppm'     => $co2Ppm,
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
            'data'      => array_merge($record, ['air_quality_status' => $status]),
            'message'   => 'Air quality reading recorded',
            'service'   => 'environment-service',
            'timestamp' => date('c'),
        ]);
    }
}
