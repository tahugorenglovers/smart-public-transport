<?php

class Alert {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function create(int $busId, string $alertType, string $severity, string $description): array {
        $stmt = $this->db->prepare("
            INSERT INTO env_alerts (bus_id, alert_type, severity, description, created_at)
            VALUES (:bus_id, :alert_type, :severity, :description, NOW())
        ");
        $stmt->execute([
            ':bus_id' => $busId,
            ':alert_type' => $alertType,
            ':severity' => $severity,
            ':description' => $description
        ]);
        $id = $this->db->lastInsertId();
        return $this->findById((int)$id);
    }

    public function findById(int $id): array {
        $stmt = $this->db->prepare("
            SELECT * FROM env_alerts WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function getActiveAlerts(): array {
        $stmt = $this->db->query("
            SELECT * FROM env_alerts
            ORDER BY created_at DESC
            LIMIT 50
        ");
        return $stmt->fetchAll();
    }
}