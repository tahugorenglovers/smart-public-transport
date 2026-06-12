<?php

class SensorValidator {
    public static function validatePassenger(?array $data): array {
        $errors = [];

        if (empty($data)) {
            return ['body' => 'Request body is required'];
        }

        if (!isset($data['bus_id']) || !is_numeric($data['bus_id']) || (int)$data['bus_id'] <= 0) {
            $errors['bus_id'] = 'bus_id must be a positive integer';
        }

        if (!isset($data['passenger_count']) || !is_numeric($data['passenger_count']) || (int)$data['passenger_count'] < 0) {
            $errors['passenger_count'] = 'passenger_count must be a non-negative integer';
        }

        return $errors;
    }

    public static function validateTemperature(?array $data): array {
        $errors = [];

        if (empty($data)) {
            return ['body' => 'Request body is required'];
        }

        if (!isset($data['bus_id']) || !is_numeric($data['bus_id']) || (int)$data['bus_id'] <= 0) {
            $errors['bus_id'] = 'bus_id must be a positive integer';
        }

        if (!isset($data['temperature']) || !is_numeric($data['temperature'])) {
            $errors['temperature'] = 'temperature must be a number';
        }

        return $errors;
    }
}