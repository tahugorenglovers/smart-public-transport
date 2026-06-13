-- shared table
CREATE TABLE IF NOT EXISTS bus_stops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stop_name VARCHAR(100) NOT NULL,
    latitude DOUBLE NOT NULL,
    longitude DOUBLE NOT NULL
);

-- seed data
INSERT INTO bus_stops (stop_name, latitude, longtitude) VALUES
('Terminal Cicaheum', -6.9022, 107.6558),
('Halte Pasar Induk', -6.9100, 107.6400),
('Halte Alun-alun', -6.9218, 107.6099),
('Terminal Leuwipanjang', -6.9600, 107.5900),
('Terminal Cibiru', -6.9190, 107.7200);