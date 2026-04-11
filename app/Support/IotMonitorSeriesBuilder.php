<?php

namespace App\Support;

use App\DataTransferObjects\SimulationStateDto;
use Carbon\CarbonImmutable;

/**
 * Synthetic time-series when telemetry DB is empty or table missing (Monitor UI).
 */
class IotMonitorSeriesBuilder
{
    /**
     * @param  CarbonImmutable|null  $end  If set, generate until this time; else use $hours
     * @return list<array{timestamp: string, value: float}>
     */
    public static function syntheticSeries(
        string $sensorKey,
        CarbonImmutable $start,
        int $hours,
        int $stepHours,
        float $temperatureMin,
        float $temperatureMax,
        ?CarbonImmutable $end = null
    ): array {
        $series = [];
        $midT = ($temperatureMin + $temperatureMax) / 2.0;
        $swingT = ($temperatureMax - $temperatureMin) / 2.0;
        if ($swingT < 0.5) {
            $swingT = 1.0;
        }

        $totalHours = $end ? (int) ceil($start->diffInSeconds($end) / 3600) : $hours;

        for ($i = 0; $i <= $totalHours; $i += $stepHours) {
            $pointTime = $start->addHours($i);
            if ($end && $pointTime->gt($end)) {
                break;
            }
            $phase = $i / max(1, $totalHours) * 2 * M_PI;

            $value = match ($sensorKey) {
                SimulationStateDto::SENSOR_TEMPERATURE => $midT + $swingT * sin($phase)
                    + mt_rand(-10, 10) / 10,
                SimulationStateDto::SENSOR_HUMIDITY => 58.0 + 7.0 * sin($phase + M_PI / 3)
                    + mt_rand(-8, 8) / 10,
                SimulationStateDto::SENSOR_CO2 => 780.0 + 120.0 * sin($phase * 0.7)
                    + $i * 0.8 + mt_rand(-15, 15),
                SimulationStateDto::SENSOR_NOISE => 41.0 + 3.0 * sin($phase * 1.5)
                    + mt_rand(-5, 5) / 10,
                SimulationStateDto::SENSOR_PRESSURE => 1013.25 + 1.2 * sin($phase)
                    + mt_rand(-3, 3) / 10,
                default => $midT,
            };

            $series[] = [
                'timestamp' => $pointTime->toIso8601String(),
                'value' => (float) $value,
            ];
        }

        return $series;
    }

    /**
     * @param  list<array{timestamp: string, value: float}>  $points
     */
    public static function roundSeriesForDisplay(array $points, int $decimals): array
    {
        $pow = 10 ** $decimals;

        return array_map(static function (array $p) use ($pow) {
            return [
                'timestamp' => $p['timestamp'],
                'value' => round((float) $p['value'] * $pow) / $pow,
            ];
        }, $points);
    }
}
