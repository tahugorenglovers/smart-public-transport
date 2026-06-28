-- Tabel ini yang paling sering diisi oleh IoT via Node-RED
CREATE TABLE IF NOT EXISTS traffic_bus_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL,
    speed DOUBLE NOT NULL DEFAULT 0,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bus_id (bus_id),
    INDEX idx_recorded_at (recorded_at),
    FOREIGN KEY (bus_id) REFERENCES traffic_buses(id) ON DELETE CASCADE
);