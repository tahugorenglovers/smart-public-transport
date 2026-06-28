<?php

require_once __DIR__ . '/../Models/PassengerReading.php';
require_once __DIR__ . '/../Models/TemperatureReading.php';
require_once __DIR__ . '/../Models/Alert.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';
require_once __DIR__ . '/../Services/SeverityHelper.php';
require_once __DIR__ . '/../Validators/SensorValidator.php';

class PassengerController {
    private PassengerReading $passengerModel;
    private TemperatureReading $temperatureModel;
    private Alert $alertModel;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->passengerModel   = new PassengerReading();
        $this->temperatureModel = new TemperatureReading();
        $this->alertModel       = new Alert();
        $this->publisher        = new RabbitMQPublisher();
    }

    public function store(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        $hasCombined = isset($body['temperature']);

        $errors = $hasCombined
            ? SensorValidator::validateEnvironment($body)
            : SensorValidator::validatePassenger($body);

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

        $busId          = (int)$body['bus_id'];
        $passengerCount = (int)$body['passenger_count'];

        // ----------------------------------------------------------------
        // 1. Save passenger reading
        // ----------------------------------------------------------------
        $passengerRecord = $this->passengerModel->create($busId, $passengerCount);
        $passengerStatus = $this->passengerModel->getStatus($passengerCount);

        // Alert rule from spec: passenger > 40 → overcrowded
        if ($passengerStatus === 'PENUH' || $passengerStatus === 'PADAT') {
            $severity    = $passengerStatus === 'PENUH' ? 'critical' : 'high';
            $description = "Bus {$busId}: {$passengerCount} penumpang — {$passengerStatus}";

            // Store in env_alerts
            $this->alertModel->create($busId, 'overcrowded', $severity, $description);

            // Publish to RabbitMQ
            $this->publisher->publish('bus.overcrowded', [
                'bus_id'          => $busId,
                'passenger_count' => $passengerCount,
                'status'          => $passengerStatus,
                'severity'        => $severity,
                'description'     => $description,
                'timestamp'       => date('c'),
            ]);
        }

        // ----------------------------------------------------------------
        // 2. Combined path: also save temperature + publish environment.updated
        // ----------------------------------------------------------------
        $responseData = array_merge($passengerRecord, ['occupancy_status' => $passengerStatus]);

        if ($hasCombined) {
            $temperature = (float)$body['temperature'];

            $temperatureRecord = $this->temperatureModel->create($busId, $temperature);
            $temperatureStatus = $this->temperatureModel->getStatus($temperature);

            $severity    = SeverityHelper::combinedSeverity($passengerCount, $temperature);
            $description = SeverityHelper::describe($severity, $passengerCount, $temperature);

            $this->publisher->publish('environment.updated', [
                'bus_id'              => $busId,
                'passenger_count'     => $passengerCount,
                'temperature'         => $temperature,
                'occupancy_status'    => $passengerStatus,
                'temperature_status'  => $temperatureStatus,
                'combined_severity'   => $severity,
                'description'         => $description,
                'timestamp'           => date('c'),
            ]);

            $responseData = array_merge($responseData, [
                'temperature'        => $temperature,
                'temperature_status' => $temperatureStatus,
                'combined_severity'  => $severity,
                'description'        => $description,
            ]);
        }

        http_response_code(201);
        echo json_encode([
            'status'    => 'success',
            'code'      => 201,
            'data'      => $responseData,
            'message'   => $hasCombined
                ? 'Environment reading recorded and environment.updated event published'
                : 'Passenger reading recorded',
            'service'   => 'environment-service',
            'timestamp' => date('c'),
        ]);
    }
}
