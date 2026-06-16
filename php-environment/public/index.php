<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Controllers/PassengerController.php';
require_once __DIR__ . '/../app/Controllers/TemperatureController.php';
require_once __DIR__ . '/../app/Controllers/AlertController.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

$passengerController = new PassengerController();
$temperatureController = new TemperatureController();
$alertController = new AlertController();

// Routing
if ($method === 'POST' && $path === '/api/environment/passenger') {
    $passengerController->store();

} elseif ($method === 'POST' && $path === '/api/environment/temperature') {
    $temperatureController->store();

} elseif ($method === 'GET' && $path === '/api/environment/alerts') {
    $alertController->index();

} elseif ($method === 'GET' && $path === '/health') {
    echo json_encode([
        'status' => 'ok',
        'service' => 'environment-service',
        'timestamp' => date('c')
    ]);

} else {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'code' => 404,
        'message' => 'Endpoint not found',
        'service' => 'environment-service'
    ]);
}