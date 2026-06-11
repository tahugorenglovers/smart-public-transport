<?php

require_once __DIR__ . '/../Models/PassengerReading.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';
require_once __DIR__ . '/../Validators/SensorValidator.php';

class PassengerController {
    private PassengerReading $model;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model = new PassengerReading();
        $this->publisher = new RabbitMQPublisher();
    }

    public function store(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        $errors = SensorValidator::validatePassenger($body);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'code' => 422,
                'message' => 'Validation failed',
                'errors' => $errors,
                'service' => 'environment-service',
                'timestamp' => date('c')
            ]);
            return;
        }

        $busId = (int)$body['bus_id'];
        $passengerCount = (int)$body['passenger_count'];

        $record = $this->model->create($busId, $passengerCount);
        $status = $this->model->getStatus($passengerCount);

        if ($status === 'PENUH' || $status === 'PADAT') {
            $this->publisher->publish('bus.overcrowded', [
                'bus_id' => $busId,
                'passenger_count' => $passengerCount,
                'status' => $status,
                'timestamp' => date('c')
            ]);
        }

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'code' => 201,
            'data' => array_merge($record, ['occupancy_status' => $status]),
            'message' => 'Passenger reading recorded',
            'service' => 'environment-service',
            'timestamp' => date('c')
        ]);
    }
}