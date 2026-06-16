<?php

namespace App\Middleware;

class AuthMiddleware {
    public static function handle(): void {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($auth) || !str_starts_with($auth, 'Bearer ')) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'code' => 401,
                'message' => 'Unauthorized: missing or invalid token',
                'data' => null,
                'service' => 'traffic-service'
            ]);
            exit;
        }
    }
}