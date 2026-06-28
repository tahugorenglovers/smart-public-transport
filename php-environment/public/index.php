<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Services/SeverityHelper.php';
require_once __DIR__ . '/../app/Controllers/PassengerController.php';
require_once __DIR__ . '/../app/Controllers/TemperatureController.php';
require_once __DIR__ . '/../app/Controllers/AirQualityController.php';
require_once __DIR__ . '/../app/Controllers/AlertController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$passengerController   = new PassengerController();
$temperatureController = new TemperatureController();
$airQualityController  = new AirQualityController();
$alertController       = new AlertController();

// Health check
if ($method === 'GET' && $path === '/health') {
    echo json_encode([
        'status'    => 'ok',
        'service'   => 'environment-service',
        'timestamp' => date('c'),
    ]);
    exit;
}

// -----------------------------------------------------------------------
// SENSOR ENDPOINTS
// These are called by Node-RED from inside the Docker network (no JWT).
// They are also reachable from the gateway via /api/environment/* for
// authenticated callers (Postman, frontend).
// -----------------------------------------------------------------------

// POST /api/environment/passenger
// Accepts: { bus_id, passenger_count } or combined { bus_id, passenger_count, temperature }
if ($method === 'POST' && $path === '/api/environment/passenger') {
    $passengerController->store();
    exit;
}

// POST /api/environment/temperature
if ($method === 'POST' && $path === '/api/environment/temperature') {
    $temperatureController->store();
    exit;
}

// POST /api/environment/air  ← NEW (Melva IoT - CO2 sensor)
if ($method === 'POST' && $path === '/api/environment/air') {
    $airQualityController->store();
    exit;
}

// GET /api/environment/alerts
if ($method === 'GET' && $path === '/api/environment/alerts') {
    $alertController->index();
    exit;
}

// 404 fallback
http_response_code(404);
echo json_encode([
    'status'  => 'error',
    'code'    => 404,
    'message' => 'Endpoint not found',
    'service' => 'environment-service',
]);
