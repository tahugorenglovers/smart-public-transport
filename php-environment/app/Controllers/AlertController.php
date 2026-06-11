<?php

require_once __DIR__ . '/../Models/Alert.php';

class AlertController {
    private Alert $model;

    public function __construct() {
        $this->model = new Alert();
    }

    public function index(): void {
        $alerts = $this->model->getActiveAlerts();

        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'data' => $alerts,
            'message' => 'Active alerts retrieved',
            'service' => 'environment-service',
            'timestamp' => date('c')
        ]);
    }
}