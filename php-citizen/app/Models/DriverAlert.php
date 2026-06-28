<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class DriverAlert
{
    private PDO $db;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = getDbConnection();
    }

    /**
     * Store a new driver behavior alert.
     */
    public function create(int $busId, string $severity, string $message): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO citizen_driver_alerts (bus_id, severity, message)
             VALUES (:bus_id, :severity, :message)'
        );
        $stmt->execute([
            ':bus_id'   => $busId,
            ':severity' => $severity,
            ':message'  => $message,
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM citizen_driver_alerts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List recent alerts, newest first.
     */
    public function findRecent(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM citizen_driver_alerts ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}