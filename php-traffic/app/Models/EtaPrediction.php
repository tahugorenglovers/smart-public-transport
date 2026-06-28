<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class EtaPrediction {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // ambil ETA terbaru untuk bus tertentu
    public function getLatestByBus(int $busId): array|false {
        $stmt = $this->db->prepare(
            "SELECT ep.*, bs.stop_name
            FROM traffic_eta_predictions ep
            LEFT JOIN bus_stops bs ON bs.id = ep.stop_id
            WHERE ep.bus_id = ?
            ORDER BY ep.predicted_at DESC
            LIMIT 1"
        );
        $stmt->execute([$busId]);
        return $stmt->fetch();
    }

    public function insert(int $busId, int $stopId, int $etaMinutes): int {
        $stmt = $this->db->prepare(
            "INSERT INTO traffic_eta_predictions 
            (bus_id, stop_id, eta_minutes, predicted_at)
            VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$busId, $stopId, $etaMinutes]);
        return (int)$this->db->lastInsertId();
    }
}