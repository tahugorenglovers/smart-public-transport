<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    /**
     * Validate Bearer JWT token.
     */
    public static function handle(): ?int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token  = substr($authHeader, 7);
        $secret = $_ENV['JWT_SECRET'] ?? '';

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (int) ($decoded->sub ?? $decoded->user_id ?? 0) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
