<?php

namespace App\Controllers;

use App\Models\Bus;
use App\Models\EtaPrediction;

class EtaController
{
    private Bus $busModel;
    private EtaPrediction $etaModel;

    public function __construct() {
        $this->busModel  = new Bus();
        $this->etaModel  = new EtaPrediction();
    }

    public function show(int $busId): void {
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

        $eta = $this->etaModel->getLatestByBus($busId);

        if (!$eta) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'code' => 404,
                'message' => 'No ETA prediction available for this bus',
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
            'message' => 'ETA retrieved successfully',
            'data' => [
                'bus_id' => $busId,
                'next_stop' => $eta['stop_name'] ?? 'Unknown',
                'eta_minutes' => (int)$eta['eta_minutes'],
            ],
            'service' => 'traffic-service',
            'timestamp' => date('c'),
        ]);
    }
}