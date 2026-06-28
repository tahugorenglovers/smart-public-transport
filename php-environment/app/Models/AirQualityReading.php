<?php

class AirQualityReading {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function create(int $busId, int $co2Ppm): array {
        $stmt = $this->db->prepare("
            INSERT INTO env_air_quality_readings (bus_id, co2_ppm, recorded_at)
            VALUES (:bus_id, :co2_ppm, NOW())
        ");
        $stmt->execute([
            ':bus_id'  => $busId,
            ':co2_ppm' => $co2Ppm,
        ]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function findById(int $id): array {
        $stmt = $this->db->prepare("SELECT * FROM env_air_quality_readings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Alert rule from the spec: CO2 > 1000 ppm → poor air quality
     *
     * Scale:
     *   NORMAL  — CO2 <= 800 ppm  (comfortable)
     *   STUFFY  — CO2 801–1000    (noticeable but tolerable)
     *   POOR    — CO2 > 1000      (spec alert threshold)
     *   HAZARDOUS — CO2 > 2000    (dangerous, occupants should evacuate)
     */
    public function getStatus(int $co2Ppm): string {
        if ($co2Ppm > 2000) return 'BERBAHAYA';
        if ($co2Ppm > 1000) return 'BURUK';     // poor air quality — alert threshold
        if ($co2Ppm > 800)  return 'PENGAP';    // stuffy
        return 'NORMAL';
    }
}
