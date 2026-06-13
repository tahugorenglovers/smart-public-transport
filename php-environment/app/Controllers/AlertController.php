<?php

require_once __DIR__ . '/../Models/Alert.php';

class AlertController {
    private Alert $model;

    public function __construct() {
        $this->model = new Alert();
    }

    public function index(): void {
        $alerts = $this->model->getActiveAlerts();

        $formatted = array_map(function ($alert) {
            return [
                'bus_id' => $alert['bus_id'],
                'alert_type' => $alert['alert_type'],
                'severity' => $alert['severity']
            ];
        }, $alerts);

        echo json_encode($formatted);
    }
}