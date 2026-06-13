<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\BusLocation;

class TrafficService {
    private Bus $busModel;
    private BusLocation $locationModel;

    public function __construct() {
        $this->busModel      = new Bus();
        $this->locationModel = new BusLocation();
    }

    public function recordLocation(
        int $busId,
        float $lat,
        float $lng,
        float $speed
    ): bool {
        $bus = $this->busModel->findById($busId);
        if (!$bus) {
            return false;
        }

        // Simpan ke database
        $this->locationModel->insert($busId, $lat, $lng, $speed);

        // Publish event ke RabbitMQ
        RabbitMQService::publish('bus.location.updated', [
            'bus_id' => $busId,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => $speed,
            'timestamp' => date('c'), // format ISO 8601
        ]);

        return true;
    }
}