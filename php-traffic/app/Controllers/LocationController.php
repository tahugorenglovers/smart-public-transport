<?php

namespace App\Controllers;

use App\Middleware\JsonMiddleware;
use App\Services\TrafficService;
use App\Validators\LocationValidator;

class LocationController {
    private TrafficService $service;

    public function __construct() {
        $this->service = new TrafficService();
    }

    public function store(): void {
        $body = JsonMiddleware::parseBody();

        // Validasi menggunakan Validator class
        $errors = LocationValidator::validate($body);
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

        $busId = (int)$body['bus_id'];
        $lat = (float)$body['latitude'];
        $lng = (float)$body['longitude'];
        $speed = (float)$body['speed'];

        $ok = $this->service->recordLocation($busId, $lat, $lng, $speed);

        if (!$ok) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'code' => 404,
                'message' => "Bus ID {$busId} not found",
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
            'message' => 'Location recorded successfully',
            'data' => null,
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }
}