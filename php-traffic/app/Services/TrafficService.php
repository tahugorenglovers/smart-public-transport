<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\BusLocation;
use App\Models\DriverTelemetry;

class TrafficService {
    private Bus $busModel;
    private BusLocation $locationModel;
    private DriverTelemetry $telemetryModel;

    public function __construct() {
        $this->busModel = new Bus();
        $this->locationModel = new BusLocation();
        $this->telemetryModel = new DriverTelemetry();
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

    public function recordTelemetry(
        int $busId,
        float $speed,
        float $acceleration,
        float $brakeForce,
        float $turnRate,
        float $vibration
    ): bool {
        $bus = $this->busModel->findById($busId);
        if (!$bus) {
            return false;
        }

        // Simpan ke database
        $this->telemetryModel->insert(
            $busId,
            $speed,
            $acceleration,
            $brakeForce,
            $turnRate,
            $vibration
        );

        RabbitMQService::publish('driver.telemetry.updated', [
            'bus_id' => $busId,
            'speed' => $speed,
            'acceleration' => $acceleration,
            'brake_force' => $brakeForce,
            'turn_rate' => $turnRate,
            'vibration' => $vibration,
            'timestamp' => date('c'),
        ]);

        return true;
    }
}