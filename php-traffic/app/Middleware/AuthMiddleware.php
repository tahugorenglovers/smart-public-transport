<?php

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class AuthMiddleware {
    public static function handle(): void {
        $headers = getallheaders();
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($auth) || !str_starts_with($auth, 'Bearer ')) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'code'    => 401,
                'message' => 'Unauthorized: missing or invalid token',
                'data'    => null,
                'service' => 'traffic-service',
            ]);
            exit;
        }

        $token  = substr($auth, 7);
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?? '';

        if (empty($secret)) {
            // Fail safe: if the secret is not configured the service should
            // not accept any tokens rather than silently allow everything.
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'code'    => 500,
                'message' => 'JWT_SECRET not configured',
                'data'    => null,
                'service' => 'traffic-service',
            ]);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            // Make decoded claims available to controllers via $_REQUEST
            $_REQUEST['_jwt_user_id'] = (int)($decoded->id ?? $decoded->sub ?? 0);
            $_REQUEST['_jwt_role']    = $decoded->role ?? 'citizen';
        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'code'    => 401,
                'message' => 'Token expired',
                'data'    => null,
                'service' => 'traffic-service',
            ]);
            exit;
        } catch (SignatureInvalidException $e) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'code'    => 401,
                'message' => 'Token signature invalid',
                'data'    => null,
                'service' => 'traffic-service',
            ]);
            exit;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'code'    => 401,
                'message' => 'Token invalid: ' . $e->getMessage(),
                'data'    => null,
                'service' => 'traffic-service',
            ]);
            exit;
        }
    }
}
