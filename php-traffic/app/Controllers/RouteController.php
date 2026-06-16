<?php

namespace App\Controllers;

use App\Models\Route;

class RouteController {
    private Route $routeModel;

    public function __construct() {
        $this->routeModel = new Route();
    }

    public function index(): void {
        $routes = $this->routeModel->findAll();

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'message' => 'Routes retrieved successfully',
            'data' => $routes,
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }

    public function show(int $id): void {
        $route = $this->routeModel->findById($id);

        if (!$route) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'code' => 404,
                'message' => 'Route not found',
                'data' => null,
                'service' => 'traffic-service',
                'timestamp' => date('c'),
            ]);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'message' => 'Route retrieved successfully',
            'data' => $route,
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }
}