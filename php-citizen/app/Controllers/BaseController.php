<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    protected function respond(
        Response $response,
        mixed    $data,
        int      $code    = 200,
        string   $message = 'OK',
        string   $status  = 'success'
    ): Response {
        $payload = json_encode([
            'status'    => $status,
            'code'      => $code,
            'data'      => $data,
            'message'   => $message,
            'timestamp' => date('c'),
            'service'   => 'citizen-service',
        ], JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($code);
    }

    protected function respondError(
        Response $response,
        int      $code,
        string   $message
    ): Response {
        return $this->respond($response, null, $code, $message, 'error');
    }

    protected function getBody(Request $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body) && !empty($body)) {
            return $body;
        }

        $raw = (string) $request->getBody();
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
