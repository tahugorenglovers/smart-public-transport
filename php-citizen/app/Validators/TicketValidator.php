<?php

declare(strict_types=1);

namespace App\Validators;

class TicketValidator
{
    public static function validateStore(array $data): array
    {
        $errors = [];

        if (empty($data['route_id'])) {
            $errors[] = 'route_id is required';
        } elseif (!is_numeric($data['route_id']) || (int) $data['route_id'] <= 0) {
            $errors[] = 'route_id must be a positive integer';
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return [
            'valid' => true,
            'data'  => [
                'route_id' => (int) $data['route_id'],
            ],
        ];
    }
}