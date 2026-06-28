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

    public static function validateAirQuality(?array $data): array {
        $errors = [];

        if (empty($data)) {
            return ['body' => 'Request body is required'];
        }

        if (!isset($data['bus_id']) || !is_numeric($data['bus_id']) || (int)$data['bus_id'] <= 0) {
            $errors['bus_id'] = 'bus_id must be a positive integer';
        }

        if (!isset($data['co2_ppm']) || !is_numeric($data['co2_ppm']) || (int)$data['co2_ppm'] < 0) {
            $errors['co2_ppm'] = 'co2_ppm must be a non-negative integer';
        }

        return $errors;
    }

    /**
     * Validate the combined environment payload (Melva's task):
     *   POST /api/environment/passenger  with body:
     *   { "bus_id": 1, "passenger_count": 40, "temperature": 31 }
     *
     * bus_id and passenger_count are required (same as the original endpoint).
     * temperature is required when this validator is used — if the caller only
     * has passenger data it should use validatePassenger() instead.
     */
    public static function validateEnvironment(?array $data): array {
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

        if (!isset($data['temperature']) || !is_numeric($data['temperature'])) {
            $errors['temperature'] = 'temperature must be a number';
        }

        return $errors;
    }
}
