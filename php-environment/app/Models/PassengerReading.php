<?php

class PassengerReading {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function create(int $busId, int $passengerCount): array {
        $stmt = $this->db->prepare("
            INSERT INTO env_passenger_readings (bus_id, passenger_count, recorded_at)
            VALUES (:bus_id, :passenger_count, NOW())
        ");
        $stmt->execute([
            ':bus_id' => $busId,
            ':passenger_count' => $passengerCount
        ]);
        $id = $this->db->lastInsertId();
        return $this->findById((int)$id);
    }

    public function findById(int $id): array {
        $stmt = $this->db->prepare("
            SELECT * FROM env_passenger_readings WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function getStatus(int $passengerCount): string {
        $full = (int)(getenv('PASSENGER_FULL_THRESHOLD') ?: 60);
        $crowded = (int)(getenv('PASSENGER_OVERCROWDED_THRESHOLD') ?: 40);

        if ($passengerCount >= $full) return 'PENUH';
        if ($passengerCount >= $crowded) return 'PADAT';
        return 'NORMAL';
    }
}