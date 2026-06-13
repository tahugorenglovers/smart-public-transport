<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class BusLocation {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function insert(int $busId, float $lat, float $lng, float $speed): int {
        $stmt = $this->db->prepare(
            "INSERT INTO traffic_bus_locations
            (bus_id, latitude, longitude, speed, recorded_at)
            VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$busId, $lat, $lng, $speed]);
        return (int)$this->db->lastInsertId();
    }

    public function getLatestAllBuses(): array {
        $sql = "SELECT bl.bus_id, bl.latitude, bl.longitude, 
                       bl.speed, bl.recorded_at,
                       b.plate_number, b.route_id, b.status
                FROM traffic_bus_locations bl
                INNER JOIN traffic_buses b ON b.id = bl.bus_id
                INNER JOIN (
                    SELECT bus_id, MAX(recorded_at) AS max_time
                    FROM traffic_bus_locations
                    GROUP BY bus_id
                ) latest ON latest.bus_id = bl.bus_id 
                        AND latest.max_time = bl.recorded_at
                WHERE b.status = 'active'";
        return $this->db->query($sql)->fetchAll();
    }

    public function getHistory(int $busId, int $limit = 10): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM traffic_bus_locations 
            WHERE bus_id = ?
            ORDER BY recorded_at DESC
            LIMIT ?"
        );
        $stmt->execute([$busId, $limit]);
        return $stmt->fetchAll();
    }
}