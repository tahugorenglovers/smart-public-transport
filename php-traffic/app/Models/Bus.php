<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Bus {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        return $this->db->query("SELECT * FROM traffic_buses")->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT * FROM traffic_buses WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare(
            "UPDATE traffic_buses SET status = ? WHERE id = ?"
        );
        return $stmt->execute([$status, $id]);
    }
}