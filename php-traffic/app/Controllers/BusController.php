<?php

namespace App\Controllers;

use App\Models\Bus;
use App\Models\BusLocation;

class BusController {
    private Bus $busModel;
    private BusLocation $locationModel;

    public function __construct() {
        $this->busModel = new Bus();
        $this->locationModel = new BusLocation();
    }

    public function current(): void {
        $locations = $this->locationModel->getLatestAllBuses();

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'message' => 'Current bus locations retrieved',
            'data' => $locations,
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }

    public function history(): void {
        $busId = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : null;
        $limit = isset($_GET['limit'])  ? (int)$_GET['limit']  : 10;

        if (!$busId) {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'code' => 422,
                'message' => "Parameter 'bus_id' is required",
                'data' => null,
                'service' => 'traffic-service',
                'timestamp' => date('c'),
            ]);
            return;
        }

        if (!$this->busModel->findById($busId)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'code' => 404,
                'message' => 'Bus not found',
                'data' => null,
                'service' => 'traffic-service',
                'timestamp' => date('c'),
            ]);
            return;
        }

        $history = $this->locationModel->getHistory($busId, $limit);

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'code' => 200,
            'message' => 'Bus history retrieved',
            'data' => $history,
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }
}