<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class MetricsSampleAggregator
{
    /** 0/1 telemetry — always max-aggregate (survives empty/stale config or config:cache). */
    private const BINARY_MAX_TYPES_DEFAULT = [
        'door_open',
        'ventilation_on',
        'pump_running',
    ];

    /**
     * Normalize a buffered JSON `value` for aggregation (bool / numeric string / int / float).
     */
    public static function scalarSampleValue(mixed $value): ?float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @param  list<float|int|string>  $values
     */
    public static function medianOfFloats(array $values): float
    {
        $nums = [];
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $nums[] = (float) $v;
            }
        }

        if ($nums === []) {
            return 0.0;
        }

        sort($nums, SORT_NUMERIC);
        $n = count($nums);
        $mid = intdiv($n, 2);

        if ($n % 2 === 1) {
            return $nums[$mid];
        }

        return ($nums[$mid - 1] + $nums[$mid]) / 2.0;
    }

    /**
     * Group raw buffered rows by (container_id, rental_id, type); one aggregate row per group.
     * Continuous sensors: median. Discrete (door / pump / vent): any "on" sample in the window → 1.
     *
     * @param  list<array<string, mixed>>  $rawRows
     * @return list<array<string, mixed>>
     */
    public static function aggregateToMedianRows(array $rawRows, DateTimeInterface $flushAt): array
    {
        $groups = [];

        foreach ($rawRows as $row) {
            if (! isset($row['container_id'], $row['type'])) {
                continue;
            }

            $cid = (int) $row['container_id'];
            $rid = isset($row['rental_id']) && is_numeric($row['rental_id'])
                ? (int) $row['rental_id']
                : null;
            $type = trim((string) $row['type']);
            $key = $cid."\0".($rid === null ? '' : (string) $rid)."\0".$type;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'container_id' => $cid,
                    'rental_id' => $rid,
                    'type' => $type,
                    'unit' => $row['unit'] ?? null,
                    'values' => [],
                    'recorded_ats' => [],
                ];
            }

            $sample = self::scalarSampleValue($row['value'] ?? null);
            if ($sample !== null) {
                $groups[$key]['values'][] = $sample;
            }

            if (isset($row['recorded_at']) && is_string($row['recorded_at']) && $row['recorded_at'] !== '') {
                $groups[$key]['recorded_ats'][] = $row['recorded_at'];
            } elseif (isset($row['recorded_at']) && $row['recorded_at'] instanceof DateTimeInterface) {
                $groups[$key]['recorded_ats'][] = $row['recorded_at']->format('Y-m-d H:i:s');
            }

            if ($groups[$key]['unit'] === null && array_key_exists('unit', $row)) {
                $groups[$key]['unit'] = $row['unit'];
            }
        }

        $stamp = CarbonImmutable::createFromInterface($flushAt);
        $out = [];

        foreach ($groups as $g) {
            if ($g['values'] === []) {
                continue;
            }

            $ats = $g['recorded_ats'];
            sort($ats);

            $binary = self::usesDiscreteOrAggregation((string) $g['type']);
            if ($binary) {
                $value = self::discreteOrAggregateValue($g['values']);
                $aggregation = 'any_on';
            } else {
                $value = self::medianOfFloats($g['values']);
                $aggregation = 'median';
            }

            $meta = [
                'aggregated' => true,
                'aggregation' => $aggregation,
                'sample_count' => count($g['values']),
                'window_first_recorded_at' => $ats === [] ? null : $ats[0],
                'window_last_recorded_at' => $ats === [] ? null : $ats[count($ats) - 1],
            ];

            $out[] = [
                'container_id' => $g['container_id'],
                'rental_id' => $g['rental_id'],
                'type' => $g['type'],
                'value' => $value,
                'unit' => $g['unit'],
                'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                'recorded_at' => $stamp->format('Y-m-d H:i:s'),
                'created_at' => $stamp->format('Y-m-d H:i:s'),
                'updated_at' => $stamp->format('Y-m-d H:i:s'),
            ];
        }

        return $out;
    }

    /**
     * True if any sample in the window indicates "on" (discrete 0/1 telemetry).
     *
     * @param  list<float>  $values
     */
    public static function discreteOrAggregateValue(array $values): float
    {
        foreach ($values as $v) {
            if ((float) $v > 0.0) {
                return 1.0;
            }
        }

        return 0.0;
    }

    /**
     * @return list<string>
     */
    public static function binaryMaxTypes(): array
    {
        $fromConfig = config('metrics_buffer.binary_sensor_types', []);
        if (! is_array($fromConfig)) {
            return self::BINARY_MAX_TYPES_DEFAULT;
        }

        $merged = [...self::BINARY_MAX_TYPES_DEFAULT];
        foreach ($fromConfig as $t) {
            $s = trim((string) $t);
            if ($s !== '' && ! in_array($s, $merged, true)) {
                $merged[] = $s;
            }
        }

        return $merged;
    }

    /**
     * Lowercased explicit discrete type names (defaults + config).
     *
     * @return list<string>
     */
    public static function discreteOrTypeNamesLowercased(): array
    {
        $out = [];
        foreach (self::binaryMaxTypes() as $t) {
            $out[] = mb_strtolower(trim($t), 'UTF-8');
        }

        return array_values(array_unique($out));
    }

    public static function usesDiscreteOrAggregation(string $type): bool
    {
        $t = mb_strtolower(trim($type), 'UTF-8');
        if ($t === '') {
            return false;
        }

        foreach (self::discreteOrTypeNamesLowercased() as $known) {
            if ($t === $known) {
                return true;
            }
        }

        $pattern = config('metrics_buffer.binary_sensor_type_pattern');
        if (is_string($pattern) && $pattern !== '') {
            $m = @preg_match($pattern, $t);
            if ($m === 1) {
                return true;
            }
        }

        return false;
    }
}
