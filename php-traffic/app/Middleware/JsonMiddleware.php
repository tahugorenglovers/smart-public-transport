<?php

namespace App\Middleware;

class JsonMiddleware {
    public static function parseBody(): array {
        $raw = file_get_contents('php://input');

        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'code' => 400,
                'message' => 'Invalid JSON body',
                'data' => null,
                'service' => 'traffic-service'
            ]);
            exit;
        }

        return $data ?? [];
    }
}