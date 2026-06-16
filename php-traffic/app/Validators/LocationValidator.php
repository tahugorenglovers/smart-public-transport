<?php

namespace App\Validators;

class LocationValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        // Validasi bus_id
        if (!isset($data['bus_id']) || !is_numeric($data['bus_id'])) {
            $errors[] = "Field 'bus_id' is required and must be a number";
        } elseif ((int)$data['bus_id'] <= 0) {
            $errors[] = "Field 'bus_id' must be greater than 0";
        }

        // Validasi latitude
        if (!isset($data['latitude']) || !is_numeric($data['latitude'])) {
            $errors[] = "Field 'latitude' is required and must be a number";
        } elseif ($data['latitude'] < -90 || $data['latitude'] > 90) {
            $errors[] = "Field 'latitude' must be between -90 and 90";
        }

        // Validasi longitude
        if (!isset($data['longitude']) || !is_numeric($data['longitude'])) {
            $errors[] = "Field 'longitude' is required and must be a number";
        } elseif ($data['longitude'] < -180 || $data['longitude'] > 180) {
            $errors[] = "Field 'longitude' must be between -180 and 180";
        }

        // Validasi speed
        if (!isset($data['speed']) || !is_numeric($data['speed'])) {
            $errors[] = "Field 'speed' is required and must be a number";
        } elseif ($data['speed'] < 0) {
            $errors[] = "Field 'speed' must be non-negative";
        }

        return $errors;
    }
}