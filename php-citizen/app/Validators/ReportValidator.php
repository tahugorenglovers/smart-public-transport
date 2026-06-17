<?php

declare(strict_types=1);

namespace App\Validators;

class ReportValidator
{
    private const VALID_CATEGORIES = [
        'halte_rusak',
        'bus_terlambat',
        'fasilitas_rusak',
        'lainnya',
    ];

    public static function validateStore(array $data): array
    {
        $errors = [];

        if (empty($data['category'])) {
            $errors[] = 'category is required';
        } elseif (!in_array($data['category'], self::VALID_CATEGORIES, true)) {
            $errors[] = 'category must be one of: ' . implode(', ', self::VALID_CATEGORIES);
        }

        if (empty($data['description'])) {
            $errors[] = 'description is required';
        } elseif (strlen($data['description']) < 10) {
            $errors[] = 'description must be at least 10 characters';
        }

        if (!empty($data['stop_id']) && (!is_numeric($data['stop_id']) || (int) $data['stop_id'] <= 0)) {
            $errors[] = 'stop_id must be a positive integer';
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }

        return [
            'valid' => true,
            'data'  => [
                'category'    => $data['category'],
                'description' => trim($data['description']),
                'stop_id'     => isset($data['stop_id']) ? (int) $data['stop_id'] : null,
            ],
        ];
    }
}