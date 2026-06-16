CREATE TABLE IF NOT EXISTS traffic_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100) NOT NULL,
    smart_point VARCHAR(100) NOT NULL,
    end_point VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- seed data
INSERT INTO traffic_routes (route_name, smart_point, end_point) VALUES
('Rute 1 - Cicaheum-Leuwipanjang', 'Terminal Cicaheum', 'Terminal Leuwipanjang'),
('Rute 2 - Cicaheum-Cibiru', 'Terminal Cicaheum', 'Terminal Cibiru'),
('Rute 3 - Leuwipanjang-Cibiru', 'Terminal Leuwipanjang', 'Terminal Cibiru');
