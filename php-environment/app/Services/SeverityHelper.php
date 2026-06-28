<?php

/**
 * SeverityHelper
 *
 * Computes a combined environment severity level from passenger load and
 * cabin temperature. Published inside the `environment.updated` RabbitMQ
 * event so downstream consumers (e.g. the ML service) can escalate driver-
 * behaviour alerts when the bus is also crowded or hot.
 *
 * Severity scale:
 *   CRITICAL  - bus full  AND temperature dangerous
 *   HIGH      - bus full  OR  temperature dangerous
 *   WARNING   - bus crowded OR temperature hot
 *   NORMAL    - everything within safe thresholds
 *
 * Thresholds are read from the same env vars used by PassengerReading and
 * TemperatureReading models so there is a single source of truth.
 */
class SeverityHelper
{
    // -----------------------------------------------------------------------
    // Internal level helpers (mirror PassengerReading / TemperatureReading)
    // -----------------------------------------------------------------------

    private static function passengerLevel(int $count): string
    {
        $full    = (int)(getenv('PASSENGER_FULL_THRESHOLD')        ?: 60);
        $crowded = (int)(getenv('PASSENGER_OVERCROWDED_THRESHOLD') ?: 40);

        if ($count >= $full)    return 'FULL';
        if ($count >= $crowded) return 'CROWDED';
        return 'NORMAL';
    }

    private static function temperatureLevel(float $temp): string
    {
        $danger = (float)(getenv('TEMP_DANGER_THRESHOLD') ?: 35);
        $hot    = (float)(getenv('TEMP_HOT_THRESHOLD')    ?: 30);

        if ($temp >= $danger) return 'DANGEROUS';
        if ($temp >= $hot)    return 'HOT';
        return 'NORMAL';
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Return combined severity string for a given passenger count and
     * cabin temperature.
     *
     * @param  int    $passengerCount  Current number of passengers on board
     * @param  float  $temperature     Cabin temperature in °C
     * @return string  CRITICAL | HIGH | WARNING | NORMAL
     */
    public static function combinedSeverity(int $passengerCount, float $temperature): string
    {
        $pLevel = self::passengerLevel($passengerCount);
        $tLevel = self::temperatureLevel($temperature);

        // Both at their worst → CRITICAL (aggressive driving here = disaster)
        if ($pLevel === 'FULL' && $tLevel === 'DANGEROUS') {
            return 'CRITICAL';
        }

        // Either at worst → HIGH
        if ($pLevel === 'FULL' || $tLevel === 'DANGEROUS') {
            return 'HIGH';
        }

        // Either mid-level → WARNING
        if ($pLevel === 'CROWDED' || $tLevel === 'HOT') {
            return 'WARNING';
        }

        return 'NORMAL';
    }

    /**
     * Human-readable description of the severity for logging / alerts.
     */
    public static function describe(string $severity, int $passengerCount, float $temperature): string
    {
        return match ($severity) {
            'CRITICAL' => "Bus penuh ({$passengerCount} penumpang) dan suhu sangat tinggi ({$temperature}°C) — kondisi kritis",
            'HIGH'     => "Bus penuh atau suhu sangat tinggi — risiko tinggi jika terjadi pengereman mendadak",
            'WARNING'  => "Bus padat atau suhu di atas normal — pantau perilaku pengemudi",
            default    => "Kondisi lingkungan normal",
        };
    }
}
