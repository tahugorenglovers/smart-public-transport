USE smarttransit;

INSERT INTO traffic_routes(route_name,start_point,end_point) VALUES
('R1','Terminal A','Terminal B'),
('R2','Terminal B','Terminal C'),
('R3','Terminal C','Terminal D');

INSERT INTO bus_stops(stop_name,latitude,longitude) VALUES
('Terminal A',-6.900,107.600),
('Cicaheum',-6.910,107.620),
('Antapani',-6.920,107.640),
('Terminal B',-6.930,107.660);

INSERT INTO traffic_buses(plate_number,route_id,status) VALUES
('D1234AA',1,'active'),
('D2345BB',2,'active'),
('D3456CC',3,'active');

INSERT INTO citizen_users(nik,name,email,password_hash,phone) VALUES
('3201','User 1','user1@mail.com','hash','0811111111'),
('3202','User 2','user2@mail.com','hash','0822222222');

INSERT INTO citizen_tickets(user_id,route_id,ticket_code,booking_time,status) VALUES
(1,1,'BUS-2026-0001',NOW(),'active');

INSERT INTO citizen_reports(user_id,category,description,stop_id,status) VALUES
(1,'halte_rusak','Atap halte bocor',1,'pending');

INSERT INTO env_passenger_readings(bus_id,passenger_count,recorded_at) VALUES
(1,32,NOW()),(2,41,NOW());

INSERT INTO env_temperature_readings(bus_id,temperature,recorded_at) VALUES
(1,28.5,NOW()),(2,30.2,NOW());

INSERT INTO traffic_bus_locations(bus_id,latitude,longitude,speed,recorded_at) VALUES
(1,-6.91,107.61,35,NOW()),
(2,-6.92,107.62,28,NOW());
