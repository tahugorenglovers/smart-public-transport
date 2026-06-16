<?php

use App\Controllers\BusController;
use App\Controllers\LocationController;
use App\Controllers\EtaController;
use App\Controllers\RouteController;
use App\Middleware\AuthMiddleware;

// Ambil method dan URI dari request
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/');

// Health Check — tidak butuh token
if ($uri === '/health' && $method === 'GET') {
    http_response_code(200);
    echo json_encode([
        'status'    => 'ok',
        'service'   => 'traffic-service',
        'timestamp' => date('c'),
    ]);
    exit;
}

// Semua route di bawah butuh JWT
AuthMiddleware::handle();

// Traffic Routes


// POST /api/traffic/location ← dari Node-RED / IoT
if ($uri === '/api/traffic/location' && $method === 'POST') {
    (new LocationController())->store();
    exit;
}

// GET /api/traffic/current ← posisi realtime semua bus
if ($uri === '/api/traffic/current' && $method === 'GET') {
    (new BusController())->current();
    exit;
}

// GET /api/traffic/eta/{bus_id} ← ETA bus ke halte berikutnya
if (preg_match('#^/api/traffic/eta/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new EtaController())->show((int)$m[1]);
    exit;
}

// GET /api/traffic/routes ← daftar semua rute
if ($uri === '/api/traffic/routes' && $method === 'GET') {
    (new RouteController())->index();
    exit;
}

// GET /api/traffic/routes/{id} ← detail rute
if (preg_match('#^/api/traffic/routes/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new RouteController())->show((int)$m[1]);
    exit;
}

// GET /api/bus/history?bus_id=1 ← riwayat perjalanan bus
if ($uri === '/api/bus/history' && $method === 'GET') {
    (new BusController())->history();
    exit;
}

// 404 fallback
http_response_code(404);
echo json_encode([
    'status' => 'error',
    'code' => 404,
    'message' => 'Endpoint not found',
    'data' => null,
    'service' => 'traffic-service',
    'timestamp' => date('c'),
]);