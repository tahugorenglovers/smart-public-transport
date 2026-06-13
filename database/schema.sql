CREATE DATABASE IF NOT EXISTS smarttransit;
USE smarttransit;

CREATE TABLE citizen_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(20) UNIQUE,
    name VARCHAR(100),
    email VARCHAR(100),
    password_hash VARCHAR(255),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE citizen_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    route_id INT,
    ticket_code VARCHAR(50),
    booking_time DATETIME,
    status ENUM('active','used','expired'),
    FOREIGN KEY(user_id) REFERENCES citizen_users(id)
);

CREATE TABLE citizen_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    category VARCHAR(50),
    description TEXT,
    stop_id INT,
    status ENUM('pending','processing','resolved'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE citizen_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE traffic_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100),
    start_point VARCHAR(100),
    end_point VARCHAR(100)
);

CREATE TABLE traffic_buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20),
    route_id INT,
    status ENUM('active','maintenance','offline'),
    FOREIGN KEY(route_id) REFERENCES traffic_routes(id)
);

CREATE TABLE bus_stops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stop_name VARCHAR(100),
    latitude DOUBLE,
    longitude DOUBLE
);

CREATE TABLE traffic_bus_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT,
    latitude DOUBLE,
    longitude DOUBLE,
    speed DOUBLE,
    recorded_at DATETIME,
    FOREIGN KEY(bus_id) REFERENCES traffic_buses(id)
);

CREATE TABLE traffic_eta_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT,
    stop_id INT,
    eta_minutes INT,
    predicted_at DATETIME,
    FOREIGN KEY(bus_id) REFERENCES traffic_buses(id),
    FOREIGN KEY(stop_id) REFERENCES bus_stops(id)
);

CREATE TABLE env_passenger_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT,
    passenger_count INT,
    recorded_at DATETIME,
    FOREIGN KEY(bus_id) REFERENCES traffic_buses(id)
);

CREATE TABLE env_temperature_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT,
    temperature DOUBLE,
    recorded_at DATETIME,
    FOREIGN KEY(bus_id) REFERENCES traffic_buses(id)
);

CREATE TABLE env_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT,
    alert_type VARCHAR(50),
    severity VARCHAR(20),
    description TEXT,
    created_at DATETIME,
    FOREIGN KEY(bus_id) REFERENCES traffic_buses(id)
);

-- ====================================
-- OAUTH SERVICE TABLES (OPTIMIZED)
-- ====================================

CREATE TABLE oauth_refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    refresh_token VARCHAR(500) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(refresh_token) -- TAMBAHKAN INI: Biar nyari refresh token pas perpanjang gak lemot
);

CREATE TABLE oauth_token_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(500) NOT NULL,
    expired_at DATETIME NOT NULL,
    blacklisted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(token) -- TAMBAHKAN INI: Paling krusial karena Gateway bakal nge-cek ini TIAP DETIK
);
