<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class BusStop {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        return $this->db->query("SELECT * FROM bus_stops")->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT * FROM bus_stops WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}