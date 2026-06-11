<?php

declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    protected function respond(
        mixed  $data,
        int    $code    = 200,
        string $message = 'OK',
        string $status  = 'success'
    ): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'status'    => $status,
            'code'      => $code,
            'data'      => $data,
            'message'   => $message,
            'timestamp' => date('c'),
            'service'   => 'citizen-service',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function respondError(int $code, string $message): void
    {
        $this->respond(null, $code, $message, 'error');
    }

    protected function getBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
