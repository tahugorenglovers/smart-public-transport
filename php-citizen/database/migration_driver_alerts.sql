-- Migration: Add citizen_driver_alerts table
-- Run this against the smarttransit / smart_transport database

CREATE TABLE IF NOT EXISTS citizen_driver_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT,
    severity VARCHAR(20),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_driver_alerts_bus_id ON citizen_driver_alerts(bus_id);
CREATE INDEX idx_driver_alerts_created_at ON citizen_driver_alerts(created_at);