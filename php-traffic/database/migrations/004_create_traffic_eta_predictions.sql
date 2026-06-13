-- Tabel ini diisi oleh ML Service setelah prediksi selesai
CREATE TABLE IF NOT EXISTS traffic_eta_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    stop_id INT NOT NULL,
    eta_minutes INT NOT NULL,
    predicted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bus_id (bus_id),
    INDEX idx_predicted_at (predicted_at),
    FOREIGN KEY (bus_id) REFERENCES traffic_buses(id) ON DELETE CASCADE
);