<?php

class TemperatureReading {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function create(int $busId, float $temperature): array {
        $stmt = $this->db->prepare("
            INSERT INTO env_temperature_readings (bus_id, temperature, recorded_at)
            VALUES (:bus_id, :temperature, NOW())
        ");
        $stmt->execute([
            ':bus_id' => $busId,
            ':temperature' => $temperature
        ]);
        $id = $this->db->lastInsertId();
        return $this->findById((int)$id);
    }

    public function findById(int $id): array {
        $stmt = $this->db->prepare("
            SELECT * FROM env_temperature_readings WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function getStatus(float $temperature): string {
        $danger = (float)(getenv('TEMP_DANGER_THRESHOLD') ?: 35);
        $hot = (float)(getenv('TEMP_HOT_THRESHOLD') ?: 30);

        if ($temperature >= $danger) return 'BERBAHAYA';
        if ($temperature >= $hot) return 'PANAS';
        return 'NORMAL';
    }
}