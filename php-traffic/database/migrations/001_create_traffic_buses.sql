CREATE TABLE IF NOT EXISTS traffic_buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    route_id INT NOT NULL,
    status ENUM('active', 'maintenance', 'offline') NOT NULL DEFAULT 'active',
    created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- seed data
INSERT INTO traffic_buses (plate_number, route_id, status) VALUES
('B 4631 UB', 1, 'active'),
('B 3423 FE', 2, 'active'),
('B 4524 fD', 2, 'maintenance'),
('B 2678 EG', 1, 'offline');