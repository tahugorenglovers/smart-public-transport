<?php

namespace App\Controllers;

use App\Middleware\JsonMiddleware;
use App\Services\TrafficService;
use App\Validators\TelemetryValidator;

class TelemetryController {
    private TrafficService $service;

    public function __construct() {
        $this->service = new TrafficService();
    }

    public function store(): void {
        $body = JsonMiddleware::parseBody();

        // Validasi input
        $errors = TelemetryValidator::validate($body);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'code' => 422,
                'message' => 'Validation failed',
                'data' => $errors,
                'service' => 'traffic-service',
                'timestamp' => date('c'),
            ]);
            return;
        }

        $ok = $this->service->recordTelemetry(
            (int)$body['bus_id'],
            (float)$body['speed'],
            (float)$body['acceleration'],
            (float)$body['brake_force'],
            (float)$body['turn_rate'],
            (float)$body['vibration']
        );

        if (!$ok) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'code' => 404,
                'message' => "Bus ID {$body['bus_id']} not found",
                'data' => null,
                'service' => 'traffic-service',
                'timestamp' => date('c'),
            ]);
            return;
        }

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'code' => 201,
            'message' => 'Telemetry recorded successfully',
            'data' => null,
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }
}