<?php

namespace App\Validators;

class TelemetryValidator {
    public static function validate(array $data): array {
        $errors = [];

        // Validasi bus_id
        if (!isset($data['bus_id']) || !is_numeric($data['bus_id'])) {
            $errors[] = "Field 'bus_id' is required and must be a number";
        } elseif ((int)$data['bus_id'] <= 0) {
            $errors[] = "Field 'bus_id' must be greater than 0";
        }

        // Validasi speed
        if (!isset($data['speed']) || !is_numeric($data['speed'])) {
            $errors[] = "Field 'speed' is required and must be a number";
        } elseif ($data['speed'] < 0) {
            $errors[] = "Field 'speed' must be non-negative";
        }

        // Validasi acceleration
        if (!isset($data['acceleration']) || !is_numeric($data['acceleration'])) {
            $errors[] = "Field 'acceleration' is required and must be a number";
        }

        // Validasi brake_force
        if (!isset($data['brake_force']) || !is_numeric($data['brake_force'])) {
            $errors[] = "Field 'brake_force' is required and must be a number";
        } elseif ($data['brake_force'] < 0) {
            $errors[] = "Field 'brake_force' must be non-negative";
        }

        // Validasi turn_rate
        if (!isset($data['turn_rate']) || !is_numeric($data['turn_rate'])) {
            $errors[] = "Field 'turn_rate' is required and must be a number";
        }

        // Validasi vibration
        if (!isset($data['vibration']) || !is_numeric($data['vibration'])) {
            $errors[] = "Field 'vibration' is required and must be a number";
        } elseif ($data['vibration'] < 0) {
            $errors[] = "Field 'vibration' must be non-negative";
        }

        return $errors;
    }
}