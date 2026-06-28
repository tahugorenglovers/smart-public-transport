<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class DriverTelemetry {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function insert(
        int $busId,
        float $speed,
        float $acceleration,
        float $brakeForce,
        float $turnRate,
        float $vibration
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO traffic_driver_telemetry 
            (bus_id, speed, acceleration, brake_force, 
            turn_rate, vibration, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $busId,
            $speed,
            $acceleration,
            $brakeForce,
            $turnRate,
            $vibration
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getByBus(int $busId, int $limit = 50): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM traffic_driver_telemetry
            WHERE bus_id = ?
            ORDER BY recorded_at DESC
            LIMIT ?"
        );
        $stmt->execute([$busId, $limit]);
        return $stmt->fetchAll();
    }

    public function getLatestByBus(int $busId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM traffic_driver_telemetry
            WHERE bus_id = ?
            ORDER BY recorded_at DESC
            LIMIT 1"
        );
        $stmt->execute([$busId]);
        return $stmt->fetch();
    }
}