<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use App\Middleware\AuthMiddleware;
use App\Controllers\TicketController;
use App\Controllers\ReportController;
use App\Controllers\NotifController;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create app
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware(); // parse JSON body        

// Error middleware
$app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// CORS middleware
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// Handle preflight
$app->options('/{routes:.+}', function ($request, $response) {
    return $response->withStatus(204);
});

// Health check (no auth)
$app->get('/health', function ($request, $response) {
    try {
        getDbConnection()->query('SELECT 1');
        $status = 'healthy';
        $code   = 200;
    } catch (\Exception $e) {
        $status = 'unhealthy';
        $code   = 503;
    }

    $payload = json_encode([
        'status'    => $status,
        'service'   => 'citizen-service',
        'timestamp' => date('c'),
    ]);

    $response->getBody()->write($payload);
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($code);
});

// Auth middleware
$auth = function ($request, $handler) {
    $userId = AuthMiddleware::handle($request);

    if ($userId === null) {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'status'    => 'error',
            'code'      => 401,
            'data'      => null,
            'message'   => 'Unauthorized - invalid or missing token',
            'timestamp' => date('c'),
            'service'   => 'citizen-service',
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }

    return $handler->handle(
        $request->withAttribute('user_id', $userId)
    );
};

// Protected routes
$app->group('/api', function (RouteCollectorProxy $group) {

    // Tickets
    $group->post('/tickets',              [TicketController::class, 'store']);
    $group->get('/tickets',               [TicketController::class, 'index']);
    $group->get('/tickets/{id}',          [TicketController::class, 'show']);
    $group->post('/tickets/{code}/scan',  [TicketController::class, 'scan']);

    // Reports
    $group->post('/reports',              [ReportController::class, 'store']);
    $group->get('/reports',               [ReportController::class, 'index']);
    $group->get('/reports/{id}',          [ReportController::class, 'show']);

    // Notifications
    $group->get('/notifications',                    [NotifController::class, 'index']);
    $group->patch('/notifications/{id}/read',        [NotifController::class, 'markRead']);

})->add($auth);

$app->run();
