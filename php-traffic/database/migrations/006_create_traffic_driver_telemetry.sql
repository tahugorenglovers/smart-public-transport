CREATE TABLE IF NOT EXISTS traffic_driver_telemetry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    speed DOUBLE,
    acceleration DOUBLE,
    brake_force DOUBLE,
    turn_rate DOUBLE,
    vibration DOUBLE,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bus_id (bus_id),
    INDEX idx_recorded_at (recorded_at),
    FOREIGN KEY (bus_id) REFERENCES traffic_buses(id) ON DELETE CASCADE
);