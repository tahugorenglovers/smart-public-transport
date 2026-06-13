<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Route {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        return $this->db->query("SELECT * FROM traffic_routes")->fetchAll();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT * FROM traffic_routes WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}