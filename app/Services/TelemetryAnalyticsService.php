<?php

namespace App\Services;

use App\DataTransferObjects\SimulationStateDto;
use App\Models\ContainerSimulationSnapshot;
use App\Models\Metric;
use App\Models\Rental;
use App\Services\Metrics\TelemetryWriteBuffer;
use App\Support\MetricsSampleAggregator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TelemetryAnalyticsService
{
    /** Widen DB time filter slightly so TZ / clock skew does not drop edge samples. */
    private const MONITOR_QUERY_BUFFER_MINUTES = 2;

    /**
     * One Redis peek per request scope (Monitor builds many sensors from the same tail).
     *
     * @var array<string, array<string, list<array<string, mixed>>>>
     */
    protected array $bufferGroupedByScopeKey = [];

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function expandMonitorQueryWindow(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            $from->copy()->subMinutes(self::MONITOR_QUERY_BUFFER_MINUTES),
            $to->copy()->addMinutes(self::MONITOR_QUERY_BUFFER_MINUTES),
        ];
    }

    /**
     * Monitor / rental telemetry: same lessee on this container (all their rentals on it), plus
     * rows with rental_id NULL (worker had no active rental). If $lesseeUserId is null, falls back
     * to strict {current rental or NULL} only (tests / legacy callers).
     */
    protected function scopeMetricsForRentalOnContainer(
        Builder $query,
        int $containerId,
        ?int $rentalId,
        ?int $lesseeUserId = null
    ): void {
        if ($rentalId === null) {
            return;
        }
        if ($lesseeUserId !== null) {
            $rentalIds = Rental::query()
                ->where('container_id', $containerId)
                ->where('user_id', $lesseeUserId)
                ->pluck('id')
                ->all();
            if ($rentalIds === []) {
                $query->where(function (Builder $q) use ($rentalId) {
                    $q->where('rental_id', $rentalId)->orWhereNull('rental_id');
                });

                return;
            }
            $query->where(function (Builder $q) use ($rentalIds) {
                $q->whereNull('rental_id')->orWhereIn('rental_id', $rentalIds);
            });

            return;
        }
        $query->where(function (Builder $q) use ($rentalId) {
            $q->where('rental_id', $rentalId)->orWhereNull('rental_id');
        });
    }

    /**
     * @param  int|null  $rentalId  Filter by rental (privacy: only current renter's data)
     * @param  int|null  $lesseeUserId  When set with rentalId, include all that user's rentals on this container + NULL rental_id
     * @return array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int}
     */
    public function latestForContainer(int $containerId, ?int $rentalId = null, ?int $lesseeUserId = null): array
    {
        $fromDb = $this->latestFromMetricsTable($containerId, $rentalId, $lesseeUserId);
        $fromBuf = $this->latestFromRedisBuffer($containerId, $rentalId, $lesseeUserId);
        $mergedDbBuf = $this->mergeLatestDbWithBuffer($fromDb, $fromBuf);
        $fromSnap = Schema::hasTable('container_simulation_snapshots')
            ? $this->latestFromSimulationSnapshot($containerId, $rentalId, $lesseeUserId)
            : ['sensors' => [], 'recorded_at' => null, 'rental_id' => null];

        return $this->mergeLatestDbAndSnapshot($mergedDbBuf, $fromSnap);
    }

    /**
     * @return array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int}
     */
    protected function latestFromMetricsTable(int $containerId, ?int $rentalId, ?int $lesseeUserId): array
    {
        $query = Metric::query()->forContainer($containerId);
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($query, $containerId, $rentalId, $lesseeUserId);
        }
        $maxAt = $query->max('recorded_at');

        if ($maxAt === null) {
            return [
                'sensors' => [],
                'recorded_at' => null,
                'rental_id' => null,
            ];
        }

        $rowsQuery = Metric::query()
            ->forContainer($containerId)
            ->where('recorded_at', $maxAt);
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($rowsQuery, $containerId, $rentalId, $lesseeUserId);
        }
        $rows = $rowsQuery->get();

        $sensors = [];
        $resolvedRentalId = null;
        foreach ($rows as $row) {
            $sensors[$row->type] = (float) $row->value;
            $resolvedRentalId ??= $row->rental_id;
        }

        return [
            'sensors' => $sensors,
            'recorded_at' => $maxAt instanceof CarbonInterface ? $maxAt->toIso8601String() : (string) $maxAt,
            'rental_id' => $resolvedRentalId,
        ];
    }

    /**
     * Live worker state for ~10s polling while `metrics` updates on the median flush cadence.
     *
     * @return array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int}
     */
    protected function latestFromSimulationSnapshot(int $containerId, ?int $rentalId, ?int $lesseeUserId): array
    {
        $snap = ContainerSimulationSnapshot::query()->where('container_id', $containerId)->first();
        if ($snap === null || $snap->last_tick_at === null) {
            return ['sensors' => [], 'recorded_at' => null, 'rental_id' => null];
        }

        $maxAge = (int) config('iot_monitor.snapshot_latest_max_age_seconds', 120);
        if ($snap->last_tick_at->diffInSeconds(now()) > $maxAge) {
            return ['sensors' => [], 'recorded_at' => null, 'rental_id' => null];
        }

        if ($rentalId !== null && ! $this->snapshotVisibleForRentalViewer($snap, $containerId, $rentalId, $lesseeUserId)) {
            return ['sensors' => [], 'recorded_at' => null, 'rental_id' => null];
        }

        $state = $snap->sensor_state ?? [];
        $sensors = [];
        foreach ($state as $key => $value) {
            if (is_numeric($value)) {
                $sensors[(string) $key] = (float) $value;
            }
        }

        return [
            'sensors' => $sensors,
            'recorded_at' => $snap->last_tick_at->toIso8601String(),
            'rental_id' => $snap->rental_id,
        ];
    }

    protected function snapshotVisibleForRentalViewer(
        ContainerSimulationSnapshot $snap,
        int $containerId,
        int $viewRentalId,
        ?int $lesseeUserId
    ): bool {
        $rid = $snap->rental_id;
        if ($lesseeUserId !== null) {
            $rentalIds = Rental::query()
                ->where('container_id', $containerId)
                ->where('user_id', $lesseeUserId)
                ->pluck('id')
                ->all();
            if ($rentalIds === []) {
                return $rid === null || $rid === $viewRentalId;
            }

            return $rid === null || in_array($rid, $rentalIds, true);
        }

        return $rid === null || $rid === $viewRentalId;
    }

    /**
     * Per-sensor: prefer Redis buffer when its sample is newer than the DB snapshot time, or tied on
     * the same wall second (legacy buffer rows used second precision only).
     *
     * @param  array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int}  $fromDb
     * @param  array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int, latest_at_by_type?: array<string, string>}  $fromBuf
     * @return array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int}
     */
    protected function mergeLatestDbWithBuffer(array $fromDb, array $fromBuf): array
    {
        $tDb = $this->parseIsoRecordedAt($fromDb['recorded_at'] ?? null);
        $merged = $fromDb['sensors'] ?? [];
        foreach ($fromBuf['latest_at_by_type'] ?? [] as $type => $iso) {
            $tB = $this->parseIsoRecordedAt($iso);
            if ($tB === null || ! isset($fromBuf['sensors'][$type])) {
                continue;
            }
            if ($tDb === null || ! $tB->lt($tDb)) {
                $merged[$type] = $fromBuf['sensors'][$type];
            }
        }

        $displayAt = $tDb;
        $tBufGlobal = $this->parseIsoRecordedAt($fromBuf['recorded_at'] ?? null);
        if ($tBufGlobal !== null && ($displayAt === null || $tBufGlobal->gt($displayAt))) {
            $displayAt = $tBufGlobal;
        }

        $recordedAt = $displayAt instanceof CarbonInterface ? $displayAt->toIso8601String() : ($fromDb['recorded_at'] ?? $fromBuf['recorded_at']);

        return [
            'sensors' => $merged,
            'recorded_at' => $recordedAt,
            'rental_id' => $fromDb['rental_id'] ?? $fromBuf['rental_id'],
        ];
    }

    protected function mergeLatestDbAndSnapshot(array $fromDb, array $fromSnap): array
    {
        $tDb = $this->parseIsoRecordedAt($fromDb['recorded_at'] ?? null);
        $tSnap = $this->parseIsoRecordedAt($fromSnap['recorded_at'] ?? null);

        $snapOk = $fromSnap['sensors'] !== [] && $tSnap !== null;
        $dbOk = $fromDb['sensors'] !== [] && $tDb !== null;

        if ($snapOk && (! $dbOk || $tSnap->gte($tDb))) {
            return [
                'sensors' => $fromSnap['sensors'],
                'recorded_at' => $fromSnap['recorded_at'],
                'rental_id' => $fromSnap['rental_id'],
            ];
        }

        if ($dbOk) {
            return [
                'sensors' => $fromDb['sensors'],
                'recorded_at' => $fromDb['recorded_at'],
                'rental_id' => $fromDb['rental_id'],
            ];
        }

        if ($snapOk) {
            return [
                'sensors' => $fromSnap['sensors'],
                'recorded_at' => $fromSnap['recorded_at'],
                'rental_id' => $fromSnap['rental_id'],
            ];
        }

        return [
            'sensors' => [],
            'recorded_at' => null,
            'rental_id' => null,
        ];
    }

    protected function parseIsoRecordedAt(?string $iso): ?CarbonInterface
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        try {
            return Carbon::parse($iso);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>|null  $sensorKeys
     * @param  int|null  $rentalId  Filter by rental (privacy: only current renter's data)
     * @param  int|null  $lesseeUserId  When set with rentalId, same scope as monitor charts
     * @return array{meta: array, series: array<string, list<array{timestamp: string, value: float, anomaly: bool}>>, variance: array<string, float>}
     */
    public function historical(
        int $containerId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?array $sensorKeys = null,
        ?float $fluctuationThreshold = null,
        string $mode = 'point',
        ?int $rentalId = null,
        ?int $lesseeUserId = null
    ): array {
        [$qFrom, $qTo] = $this->expandMonitorQueryWindow($from, $to);

        $query = Metric::query()
            ->forContainer($containerId)
            ->between($qFrom, $qTo)
            ->orderBy('recorded_at');
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($query, $containerId, $rentalId, $lesseeUserId);
        }
        if ($sensorKeys !== null && $sensorKeys !== []) {
            $query->forType($sensorKeys);
        }

        /** @var Collection<int, Metric> $readings */
        $readings = $query->get();
        $readings = $readings
            ->filter(fn (Metric $r) => $r->recorded_at >= $from && $r->recorded_at <= $to)
            ->values();

        $grouped = $readings->groupBy('type');
        $series = [];
        $variance = [];

        foreach ($grouped as $key => $items) {
            $values = $items->pluck('value')->map(fn ($v) => (float) $v)->values()->all();
            $variance[$key] = $this->variance($values);

            $points = [];
            $prev = null;
            foreach ($items as $item) {
                $val = (float) $item->value;
                $anomaly = false;
                if ($fluctuationThreshold !== null && $prev !== null) {
                    if ($mode === 'point') {
                        $anomaly = abs($val - $prev) > $fluctuationThreshold;
                    }
                }
                $points[] = [
                    'timestamp' => $item->recorded_at->toIso8601String(),
                    'value' => $val,
                    'anomaly' => $anomaly,
                ];
                $prev = $val;
            }

            if ($mode === 'window' && $fluctuationThreshold !== null && count($points) >= 3) {
                $points = $this->applyWindowVarianceAnomalies($points, $fluctuationThreshold);
            }

            $series[$key] = $points;
        }

        return [
            'meta' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'fluctuation_threshold' => $fluctuationThreshold,
                'mode' => $mode,
            ],
            'series' => $series,
            'variance' => $variance,
        ];
    }

    /**
     * @param  list<array{timestamp: string, value: float, anomaly: bool}>  $points
     * @return list<array{timestamp: string, value: float, anomaly: bool}>
     */
    protected function applyWindowVarianceAnomalies(array $points, float $threshold): array
    {
        $n = count($points);
        for ($i = 1; $i < $n - 1; $i++) {
            $window = [$points[$i - 1]['value'], $points[$i]['value'], $points[$i + 1]['value']];
            if ($this->variance($window) > $threshold) {
                $points[$i]['anomaly'] = true;
            }
        }

        return $points;
    }

    /**
     * @param  list<float>  $values
     */
    protected function variance(array $values): float
    {
        $c = count($values);
        if ($c < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $c;
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return $sum / ($c - 1);
    }

    /**
     * Summary stats for a monitor time series (fluctuation / exploration strip).
     *
     * @param  list<array{timestamp: string, value: float}>  $points
     * @return array{min: ?float, max: ?float, mean: ?float, last: ?float, variance: float, count: int}
     */
    public function seriesStats(array $points): array
    {
        if ($points === []) {
            return [
                'min' => null,
                'max' => null,
                'mean' => null,
                'last' => null,
                'variance' => 0.0,
                'count' => 0,
            ];
        }

        $values = array_map(static fn (array $p) => (float) $p['value'], $points);
        $count = count($values);
        $min = min($values);
        $max = max($values);
        $mean = array_sum($values) / $count;
        $last = (float) $points[$count - 1]['value'];

        return [
            'min' => $min,
            'max' => $max,
            'mean' => $mean,
            'last' => $last,
            'variance' => $this->variance($values),
            'count' => $count,
        ];
    }

    /** Synthetic points before the oldest *displayed* sample (keeps total ≤ chart max). */
    public const MONITOR_DEFAULT_PREFIX_POINTS = 0;

    public function monitorChartMaxPoints(): int
    {
        $n = (int) config('iot_monitor.chart_max_points', 30);

        return max(5, min(500, $n));
    }

    /**
     * Chart series: time-bucketed to {@see monitorChartMaxPoints()} across [from, to] (merged with buffer).
     * Stats reflect strict-window samples loaded from DB (newest tail if over cap) plus buffer rows in-window.
     * Extended lookback keeps a raw tail (no bucketing) when the strict window has no DB rows.
     *
     * @param  int|null  $lesseeUserId  Renter user id (same scope as latest/historical when set)
     * @param  string  $seriesMode  window: time bucket in [from,to]; raw_tail: newest chart_max_points merged DB+buffer (ignores from)
     * @return array{series: list<array{timestamp: string, value: float}>, stats: array{min: ?float, max: ?float, mean: ?float, last: ?float, variance: float, count: int}, stats_truncated: bool, discrete: bool, telemetry_backed: bool, samples_in_range: int, buffer_samples_in_range: int, chart_max_points: int, used_extended_lookback: bool}
     */
    public function chartSeriesForMonitor(
        int $containerId,
        string $sensorKey,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        ?int $rentalId,
        ?int $lesseeUserId = null,
        string $seriesMode = 'window'
    ): array {
        $chartCap = $this->monitorChartMaxPoints();
        $discrete = MetricsSampleAggregator::usesDiscreteOrAggregation($sensorKey);
        $seriesMode = $seriesMode === 'raw_tail' ? 'raw_tail' : 'window';
        $table = (new Metric)->getTable();
        $to = $to ?? Carbon::now();
        $from = $from ?? $to->copy()->subHours(24);

        if ($seriesMode === 'raw_tail') {
            if (! Schema::hasTable($table)) {
                $seed = $this->buildSeedOnlySeries($from, $to, $sensorKey);

                return [
                    'series' => $seed,
                    'stats' => $this->seriesStats($seed),
                    'stats_truncated' => false,
                    'discrete' => $discrete,
                    'telemetry_backed' => false,
                    'samples_in_range' => 0,
                    'buffer_samples_in_range' => 0,
                    'chart_max_points' => $chartCap,
                    'used_extended_lookback' => false,
                ];
            }

            return $this->chartSeriesRawTail($containerId, $sensorKey, $to, $rentalId, $lesseeUserId, $chartCap, $discrete);
        }

        if (! Schema::hasTable($table)) {
            $seed = $this->buildSeedOnlySeries($from ?? Carbon::now()->subDay(), $to ?? Carbon::now(), $sensorKey);

            return [
                'series' => $seed,
                'stats' => $this->seriesStats($seed),
                'stats_truncated' => false,
                'discrete' => $discrete,
                'telemetry_backed' => false,
                'samples_in_range' => 0,
                'buffer_samples_in_range' => 0,
                'chart_max_points' => $chartCap,
                'used_extended_lookback' => false,
            ];
        }

        $bufferGrouped = $rentalId !== null
            ? $this->bufferGroupedForMonitorScope($containerId, $rentalId, $lesseeUserId)
            : [];
        /** @var list<array<string, mixed>> $sensorBufferRows */
        $sensorBufferRows = $bufferGrouped[$sensorKey] ?? [];
        $bufferSamplesStrict = $this->countBufferSamplesInStrictWindow($sensorBufferRows, $from, $to);

        $samplesInRange = $this->countMonitorMetricsInStrictWindow(
            $containerId,
            $sensorKey,
            $from,
            $to,
            $rentalId,
            $lesseeUserId
        );

        $maxSamples = $this->monitorChartWindowSamplesMax($chartCap);
        $statsTruncated = $samplesInRange > $maxSamples;

        $readings = $this->fetchMonitorMetricsChronologicalInWindow(
            $containerId,
            $sensorKey,
            $from,
            $to,
            $rentalId,
            $lesseeUserId,
            $maxSamples,
            $samplesInRange
        );

        $usedExtendedLookback = false;

        if ($readings->isEmpty()) {
            $lookbackDays = max(1, (int) config('iot_monitor.extended_lookback_days', 7));
            $extendedFrom = $to->copy()->subDays($lookbackDays);
            $readings = $this->fetchLatestMonitorMetricsOpenEnded(
                $containerId,
                $sensorKey,
                $extendedFrom,
                $to,
                $rentalId,
                $lesseeUserId,
                $chartCap
            );
            $usedExtendedLookback = $readings->isNotEmpty();
            $statsTruncated = false;
        }

        $defaultVal = $this->defaultValueForSensorKey($sensorKey);
        $prefixN = self::MONITOR_DEFAULT_PREFIX_POINTS;

        if ($readings->isEmpty()) {
            $bufferPts = $this->bufferRowsToChartPoints($sensorBufferRows, $from, $to);
            if ($bufferPts !== []) {
                $stats = $this->seriesStats($bufferPts);
                if ($sensorKey === SimulationStateDto::SENSOR_DOOR_OPEN) {
                    $series = $this->doorOpenChartAsTransitions($bufferPts, $from, $to, $chartCap);
                } elseif (count($bufferPts) <= $chartCap) {
                    $series = array_values($bufferPts);
                } else {
                    $series = $this->bucketDownsampleMonitorSeries($bufferPts, $from, $to, $chartCap, $sensorKey);
                }

                return [
                    'series' => $series,
                    'stats' => $stats,
                    'stats_truncated' => false,
                    'discrete' => $discrete,
                    'telemetry_backed' => true,
                    'samples_in_range' => $samplesInRange + $bufferSamplesStrict,
                    'buffer_samples_in_range' => $bufferSamplesStrict,
                    'chart_max_points' => $chartCap,
                    'used_extended_lookback' => false,
                ];
            }

            $seed = $this->buildSeedOnlySeries($from, $to, $sensorKey, min(4, max(3, $prefixN ?: 4)));

            return [
                'series' => $seed,
                'stats' => $this->seriesStats($seed),
                'stats_truncated' => false,
                'discrete' => $discrete,
                'telemetry_backed' => false,
                'samples_in_range' => $samplesInRange,
                'buffer_samples_in_range' => $bufferSamplesStrict,
                'chart_max_points' => $chartCap,
                'used_extended_lookback' => false,
            ];
        }

        $real = $readings->map(fn (Metric $r) => [
            'timestamp' => $r->recorded_at->toIso8601String(),
            'value' => round((float) $r->value, 4),
        ])->values()->all();

        $prefix = [];
        if ($prefixN > 0 && $readings->isNotEmpty()) {
            $firstAt = $readings->first()->recorded_at;
            $prefix = $this->buildDefaultPrefixPointsBeforeFirst($firstAt, $defaultVal, $prefixN);
        }

        $bufferPts = $this->bufferRowsToChartPoints($sensorBufferRows, $from, $to);
        $merged = array_merge($prefix, $real, $bufferPts);
        $dedup = [];
        foreach ($merged as $p) {
            if (! isset($p['timestamp'])) {
                continue;
            }
            $dedup[$p['timestamp']] = $p;
        }
        ksort($dedup);
        $merged = array_values($dedup);

        if ($usedExtendedLookback) {
            if (count($merged) > $chartCap) {
                $merged = array_slice($merged, -$chartCap);
            }
            $series = array_values($merged);

            return [
                'series' => $series,
                'stats' => $this->seriesStats($series),
                'stats_truncated' => false,
                'discrete' => $discrete,
                'telemetry_backed' => true,
                'samples_in_range' => $samplesInRange + $bufferSamplesStrict,
                'buffer_samples_in_range' => $bufferSamplesStrict,
                'chart_max_points' => $chartCap,
                'used_extended_lookback' => true,
            ];
        }

        /** @var list<array{timestamp: string, value: float}> $strictPts */
        $strictPts = [];
        foreach ($merged as $p) {
            $t = CarbonImmutable::parse($p['timestamp']);
            if ($t->gte($from) && $t->lte($to)) {
                $strictPts[] = $p;
            }
        }

        $stats = $this->seriesStats($strictPts);

        if ($sensorKey === SimulationStateDto::SENSOR_DOOR_OPEN) {
            $series = $this->doorOpenChartAsTransitions($strictPts, $from, $to, $chartCap);
        } elseif (count($strictPts) <= $chartCap) {
            $series = $strictPts;
        } else {
            $series = $this->bucketDownsampleMonitorSeries($strictPts, $from, $to, $chartCap, $sensorKey);
        }

        return [
            'series' => array_values($series),
            'stats' => $stats,
            'stats_truncated' => $statsTruncated,
            'discrete' => $discrete,
            'telemetry_backed' => true,
            'samples_in_range' => $samplesInRange + $bufferSamplesStrict,
            'buffer_samples_in_range' => $bufferSamplesStrict,
            'chart_max_points' => $chartCap,
            'used_extended_lookback' => false,
        ];
    }

    /**
     * Newest {@see monitorChartMaxPoints()} raw samples (DB + Redis buffer), merged by timestamp (buffer wins ties).
     *
     * @return array{series: list<array{timestamp: string, value: float}>, stats: array{min: ?float, max: ?float, mean: ?float, last: ?float, variance: float, count: int}, stats_truncated: bool, discrete: bool, telemetry_backed: bool, samples_in_range: int, buffer_samples_in_range: int, chart_max_points: int, used_extended_lookback: bool}
     */
    protected function chartSeriesRawTail(
        int $containerId,
        string $sensorKey,
        CarbonInterface $to,
        ?int $rentalId,
        ?int $lesseeUserId,
        int $chartCap,
        bool $discrete
    ): array {
        $toCi = CarbonImmutable::createFromInterface($to);
        $lookbackFrom = $toCi->copy()->subYears(2);
        [$qFrom, $qTo] = $this->expandMonitorQueryWindow($lookbackFrom, $toCi);
        $fetchN = max(200, $chartCap * 15);

        $readings = $this->fetchLatestMonitorMetrics(
            $containerId,
            $sensorKey,
            $qFrom,
            $qTo,
            $rentalId,
            $lesseeUserId,
            $fetchN
        );
        $readings = $readings
            ->filter(fn (Metric $r) => $r->recorded_at <= $toCi)
            ->sortBy('recorded_at')
            ->values();

        $dbPts = $readings->map(fn (Metric $r) => [
            'timestamp' => $r->recorded_at->toIso8601String(),
            'value' => round((float) $r->value, 4),
        ])->values()->all();

        $bufferGrouped = $rentalId !== null
            ? $this->bufferGroupedForMonitorScope($containerId, $rentalId, $lesseeUserId)
            : [];
        /** @var list<array<string, mixed>> $sensorBufferRows */
        $sensorBufferRows = $bufferGrouped[$sensorKey] ?? [];
        $bufPts = $this->bufferRowsToChartPointsUpTo($sensorBufferRows, $toCi);

        /** @var array<string, array{value: float, buf: bool}> $byTs */
        $byTs = [];
        foreach ($dbPts as $p) {
            $byTs[$p['timestamp']] = ['value' => $p['value'], 'buf' => false];
        }
        foreach ($bufPts as $p) {
            $byTs[$p['timestamp']] = ['value' => $p['value'], 'buf' => true];
        }
        ksort($byTs);
        /** @var list<array{timestamp: string, value: float}> $merged */
        $merged = [];
        foreach ($byTs as $ts => $meta) {
            $merged[] = ['timestamp' => $ts, 'value' => $meta['value'], '_buf' => $meta['buf']];
        }

        if ($merged === []) {
            $seed = $this->buildSeedOnlySeries($toCi->copy()->subHours(6), $toCi, $sensorKey, 4);

            return [
                'series' => $seed,
                'stats' => $this->seriesStats($seed),
                'stats_truncated' => false,
                'discrete' => $discrete,
                'telemetry_backed' => false,
                'samples_in_range' => 0,
                'buffer_samples_in_range' => 0,
                'chart_max_points' => $chartCap,
                'used_extended_lookback' => false,
            ];
        }

        $tail = array_slice($merged, -$chartCap);
        $bufInTail = 0;
        foreach ($tail as $p) {
            if (! empty($p['_buf'])) {
                $bufInTail++;
            }
        }
        $series = [];
        foreach ($tail as $p) {
            $series[] = ['timestamp' => $p['timestamp'], 'value' => $p['value']];
        }

        $stats = $this->seriesStats($series);

        return [
            'series' => array_values($series),
            'stats' => $stats,
            'stats_truncated' => count($merged) > $chartCap,
            'discrete' => $discrete,
            'telemetry_backed' => true,
            'samples_in_range' => count($merged),
            'buffer_samples_in_range' => $bufInTail,
            'chart_max_points' => $chartCap,
            'used_extended_lookback' => false,
        ];
    }

    /**
     * @param  list<array{timestamp: string, value: float}>  $strictPts
     * @return list<array{timestamp: string, value: float}>
     */
    protected function doorOpenChartAsTransitions(
        array $strictPts,
        CarbonInterface $from,
        CarbonInterface $to,
        int $maxPoints
    ): array {
        if ($strictPts === []) {
            return [];
        }

        usort($strictPts, static fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

        /** @var list<array{timestamp: string, value: float}> $norm */
        $norm = [];
        foreach ($strictPts as $p) {
            $norm[] = [
                'timestamp' => $p['timestamp'],
                'value' => ((float) $p['value'] >= 0.5) ? 1.0 : 0.0,
            ];
        }

        /** @var list<array{timestamp: string, value: float}> $events */
        $events = [];
        $prev = null;
        foreach ($norm as $p) {
            $s = $p['value'];
            if ($prev === null) {
                $events[] = $p;
                $prev = $s;

                continue;
            }
            if ($s !== $prev) {
                $events[] = $p;
                $prev = $s;
            }
        }

        $lastRaw = $norm[count($norm) - 1];
        $lastEmitted = $events[count($events) - 1];
        if ($lastEmitted['timestamp'] !== $lastRaw['timestamp']) {
            $events[] = $lastRaw;
        }

        $fromCi = CarbonImmutable::createFromInterface($from);
        $toCi = CarbonImmutable::createFromInterface($to);

        /** @var list<array{timestamp: string, value: float}> $full */
        $full = [];
        $firstEv = $events[0];
        if (CarbonImmutable::parse($firstEv['timestamp'])->gt($fromCi)) {
            $full[] = [
                'timestamp' => $fromCi->toIso8601String(),
                'value' => $firstEv['value'],
            ];
        }
        foreach ($events as $e) {
            $full[] = $e;
        }
        if (CarbonImmutable::parse($lastRaw['timestamp'])->lt($toCi)) {
            $full[] = [
                'timestamp' => $toCi->toIso8601String(),
                'value' => $lastRaw['value'],
            ];
        }

        usort($full, static fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

        if (count($full) > $maxPoints) {
            $full = array_slice($full, -$maxPoints);
        }

        return array_values($full);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{timestamp: string, value: float}>
     */
    protected function bufferRowsToChartPointsUpTo(array $rows, CarbonInterface $to): array
    {
        $toCi = CarbonImmutable::createFromInterface($to);
        $pts = [];
        foreach ($rows as $row) {
            $at = $this->parseBufferRowRecordedAt($row);
            if ($at === null || $at->gt($toCi)) {
                continue;
            }
            if (! isset($row['value']) || ! is_numeric($row['value'])) {
                continue;
            }
            $pts[] = [
                'timestamp' => $at->toIso8601String(),
                'value' => round((float) $row['value'], 4),
            ];
        }
        usort($pts, static fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

        return $pts;
    }

    protected function monitorChartWindowSamplesMax(int $chartCap): int
    {
        $n = (int) config('iot_monitor.chart_window_samples_max', 10000);

        return max($chartCap, min(50000, $n));
    }

    /**
     * Strict-window DB rows, chronological. When there are more than $maxSamples rows, keeps the **newest** tail.
     *
     * @return Collection<int, Metric>
     */
    protected function fetchMonitorMetricsChronologicalInWindow(
        int $containerId,
        string $sensorKey,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $rentalId,
        ?int $lesseeUserId,
        int $maxSamples,
        int $totalInWindow
    ): Collection {
        $query = Metric::query()
            ->forContainer($containerId)
            ->forType($sensorKey)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $to);
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($query, $containerId, $rentalId, $lesseeUserId);
        }

        if ($totalInWindow > $maxSamples) {
            $skip = max(0, $totalInWindow - $maxSamples);

            return (clone $query)->orderBy('recorded_at')->skip($skip)->take($maxSamples)->get();
        }

        return $query->orderBy('recorded_at')->get();
    }

    /**
     * @param  list<float|int|string>  $values
     */
    protected function meanOfFloats(array $values): float
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

        return array_sum($nums) / count($nums);
    }

    /**
     * @param  list<array{timestamp: string, value: float}>  $sortedPoints  strict [from, to] only
     * @return list<array{timestamp: string, value: float}>
     */
    protected function bucketDownsampleMonitorSeries(
        array $sortedPoints,
        CarbonInterface $from,
        CarbonInterface $to,
        int $bucketCount,
        string $sensorKey
    ): array {
        if ($bucketCount < 1 || $sortedPoints === []) {
            return [];
        }

        usort($sortedPoints, static fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

        $fromCi = CarbonImmutable::createFromInterface($from);
        $toCi = CarbonImmutable::createFromInterface($to);
        $fromSec = $fromCi->getTimestamp();
        $toSec = $toCi->getTimestamp();
        $spanSec = max(1, $toSec - $fromSec);
        $bucketWidthSec = $spanSec / $bucketCount;

        $discrete = MetricsSampleAggregator::usesDiscreteOrAggregation($sensorKey);
        $mode = mb_strtolower(trim((string) config('iot_monitor.chart_bucket_continuous', 'median')));
        $useMean = ! $discrete && $mode === 'mean';

        /** @var array<int, list<float>> $groups */
        $groups = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $groups[$i] = [];
        }

        foreach ($sortedPoints as $p) {
            $t = CarbonImmutable::parse($p['timestamp']);
            if ($t->lt($fromCi) || $t->gt($toCi)) {
                continue;
            }
            $offsetSec = $t->getTimestamp() - $fromSec;
            $idx = (int) floor($offsetSec / $bucketWidthSec);
            if ($idx < 0) {
                $idx = 0;
            }
            if ($idx >= $bucketCount) {
                $idx = $bucketCount - 1;
            }
            $groups[$idx][] = (float) $p['value'];
        }

        /** @var list<float|null> $agg */
        $agg = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            if ($groups[$i] === []) {
                $agg[] = null;
            } elseif ($discrete) {
                $agg[] = MetricsSampleAggregator::discreteOrAggregateValue($groups[$i]);
            } elseif ($useMean) {
                $agg[] = $this->meanOfFloats($groups[$i]);
            } else {
                $agg[] = MetricsSampleAggregator::medianOfFloats($groups[$i]);
            }
        }

        /** @var list<float> $filled */
        $filled = array_fill(0, $bucketCount, 0.0);

        if ($discrete) {
            $carry = 0.0;
            for ($i = 0; $i < $bucketCount; $i++) {
                if ($agg[$i] !== null) {
                    $carry = (float) $agg[$i];
                }
                $filled[$i] = $carry;
            }
        } else {
            $f = null;
            $l = null;
            for ($i = 0; $i < $bucketCount; $i++) {
                if ($agg[$i] !== null) {
                    $f = $i;
                    break;
                }
            }
            for ($i = $bucketCount - 1; $i >= 0; $i--) {
                if ($agg[$i] !== null) {
                    $l = $i;
                    break;
                }
            }
            if ($f === null || $l === null) {
                return [];
            }
            for ($i = 0; $i < $f; $i++) {
                $filled[$i] = (float) $agg[$f];
            }
            for ($i = $l + 1; $i < $bucketCount; $i++) {
                $filled[$i] = (float) $agg[$l];
            }
            $carry = (float) $agg[$f];
            for ($i = $f; $i <= $l; $i++) {
                if ($agg[$i] !== null) {
                    $carry = (float) $agg[$i];
                }
                $filled[$i] = $carry;
            }
        }

        $out = [];
        for ($i = 0; $i < $bucketCount; $i++) {
            $midOffset = ($i + 0.5) * $bucketWidthSec;
            $ts = $fromCi->addSeconds((int) round($midOffset));
            if ($ts->gt($toCi)) {
                $ts = $toCi;
            }
            $out[] = [
                'timestamp' => $ts->toIso8601String(),
                'value' => round($filled[$i], 4),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    protected function bufferGroupedForMonitorScope(int $containerId, ?int $rentalId, ?int $lesseeUserId): array
    {
        if ($rentalId === null) {
            return [];
        }
        $k = implode(':', [(string) $containerId, (string) $rentalId, (string) ($lesseeUserId ?? '')]);
        if (! isset($this->bufferGroupedByScopeKey[$k])) {
            $this->bufferGroupedByScopeKey[$k] = app(TelemetryWriteBuffer::class)
                ->peekGroupedSamplesForScope($containerId, $rentalId, $lesseeUserId, (int) config('metrics_buffer.peek_max_tail', 8000));
        }

        return $this->bufferGroupedByScopeKey[$k];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function countBufferSamplesInStrictWindow(array $rows, CarbonInterface $from, CarbonInterface $to): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $at = $this->parseBufferRowRecordedAt($row);
            if ($at !== null && $at->gte($from) && $at->lte($to)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{timestamp: string, value: float}>
     */
    protected function bufferRowsToChartPoints(array $rows, CarbonInterface $from, CarbonInterface $to): array
    {
        $pts = [];
        foreach ($rows as $row) {
            $at = $this->parseBufferRowRecordedAt($row);
            if ($at === null || $at->lt($from) || $at->gt($to)) {
                continue;
            }
            if (! isset($row['value']) || ! is_numeric($row['value'])) {
                continue;
            }
            $pts[] = [
                'timestamp' => $at->toIso8601String(),
                'value' => round((float) $row['value'], 4),
            ];
        }
        usort($pts, fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

        return $pts;
    }

    protected function parseBufferRowRecordedAt(array $row): ?CarbonInterface
    {
        $v = $row['recorded_at'] ?? null;
        if ($v instanceof CarbonInterface) {
            return $v->copy();
        }
        if (is_string($v) && $v !== '') {
            try {
                return Carbon::parse($v);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Latest per-sensor values from the Redis tail (not yet flushed to metrics).
     *
     * @return array{sensors: array<string, float>, recorded_at: ?string, rental_id: ?int}
     */
    protected function latestFromRedisBuffer(int $containerId, ?int $rentalId, ?int $lesseeUserId): array
    {
        if ($rentalId === null) {
            return ['sensors' => [], 'recorded_at' => null, 'rental_id' => null, 'latest_at_by_type' => []];
        }

        $grouped = $this->bufferGroupedForMonitorScope($containerId, $rentalId, $lesseeUserId);
        $sensors = [];
        $latestAtByType = [];
        $maxAt = null;
        $resolvedRentalId = null;
        foreach ($grouped as $typeKey => $rows) {
            $best = null;
            $bestAt = null;
            foreach ($rows as $row) {
                $at = $this->parseBufferRowRecordedAt($row);
                if ($at === null) {
                    continue;
                }
                if ($bestAt === null || $at->gt($bestAt)) {
                    $bestAt = $at;
                    $best = $row;
                }
            }
            if ($best !== null && isset($best['value']) && is_numeric($best['value'])) {
                $type = (string) ($best['type'] ?? $typeKey);
                if ($type === '') {
                    continue;
                }
                $sensors[$type] = (float) $best['value'];
                $latestAtByType[$type] = $bestAt->toIso8601String();
                if ($maxAt === null || $bestAt->gt($maxAt)) {
                    $maxAt = $bestAt;
                }
                $resolvedRentalId ??= isset($best['rental_id']) && is_numeric($best['rental_id'])
                    ? (int) $best['rental_id']
                    : null;
            }
        }

        return [
            'sensors' => $sensors,
            'recorded_at' => $maxAt instanceof CarbonInterface ? $maxAt->toIso8601String() : null,
            'rental_id' => $resolvedRentalId,
            'latest_at_by_type' => $latestAtByType,
        ];
    }

    /**
     * @return Collection<int, Metric>
     */
    protected function fetchLatestMonitorMetrics(
        int $containerId,
        string $sensorKey,
        CarbonInterface $qFrom,
        CarbonInterface $qTo,
        ?int $rentalId,
        ?int $lesseeUserId,
        int $limit
    ): Collection {
        $query = Metric::query()
            ->forContainer($containerId)
            ->forType($sensorKey)
            ->where('recorded_at', '>=', $qFrom)
            ->where('recorded_at', '<=', $qTo);
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($query, $containerId, $rentalId, $lesseeUserId);
        }

        $ids = (clone $query)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Metric::query()
            ->whereIn('id', $ids)
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Latest N rows with recorded_at in [extendedFrom, to] (strict bounds, no buffer).
     *
     * @return Collection<int, Metric>
     */
    protected function fetchLatestMonitorMetricsOpenEnded(
        int $containerId,
        string $sensorKey,
        CarbonInterface $extendedFrom,
        CarbonInterface $to,
        ?int $rentalId,
        ?int $lesseeUserId,
        int $limit
    ): Collection {
        $query = Metric::query()
            ->forContainer($containerId)
            ->forType($sensorKey)
            ->where('recorded_at', '>=', $extendedFrom)
            ->where('recorded_at', '<=', $to);
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($query, $containerId, $rentalId, $lesseeUserId);
        }

        $ids = (clone $query)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Metric::query()
            ->whereIn('id', $ids)
            ->orderBy('recorded_at')
            ->get();
    }

    protected function countMonitorMetricsInStrictWindow(
        int $containerId,
        string $sensorKey,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $rentalId,
        ?int $lesseeUserId
    ): int {
        $query = Metric::query()
            ->forContainer($containerId)
            ->forType($sensorKey)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $to);
        if ($rentalId !== null) {
            $this->scopeMetricsForRentalOnContainer($query, $containerId, $rentalId, $lesseeUserId);
        }

        return (int) $query->count();
    }

    /**
     * @return list<array{timestamp: string, value: float}>
     */
    public function sensorSeriesForMonitor(
        int $containerId,
        string $sensorKey,
        int $hours = 24,
        int $stepHours = 2,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        ?int $rentalId = null,
        ?int $lesseeUserId = null
    ): array {
        $to = $to ?? Carbon::now();
        $from = $from ?? $to->copy()->subHours($hours);

        return $this->chartSeriesForMonitor($containerId, $sensorKey, $from, $to, $rentalId, $lesseeUserId)['series'];
    }

    protected function defaultValueForSensorKey(string $sensorKey): float
    {
        $initial = config('simulation.initial', []);

        return (float) ($initial[$sensorKey] ?? match ($sensorKey) {
            SimulationStateDto::SENSOR_TEMPERATURE => (float) ($initial['temperature_c'] ?? 6.0),
            SimulationStateDto::SENSOR_HUMIDITY => (float) ($initial['humidity_rh'] ?? 62.0),
            SimulationStateDto::SENSOR_CO2 => (float) ($initial['co2_ppm'] ?? 720.0),
            SimulationStateDto::SENSOR_NOISE => (float) ($initial['noise_db'] ?? 42.0),
            SimulationStateDto::SENSOR_PRESSURE => (float) ($initial['pressure_hpa'] ?? 1013.25),
            default => 0.0,
        });
    }

    /**
     * @return list<array{timestamp: string, value: float}>
     */
    protected function buildSeedOnlySeries(CarbonInterface $from, CarbonInterface $to, string $sensorKey, int $count = 4): array
    {
        $count = max(3, min(4, $count));
        $v = $this->defaultValueForSensorKey($sensorKey);
        $end = $to->copy();
        $start = $end->copy()->subMinutes(3);
        if ($start->lt($from)) {
            $start = $from->copy();
        }
        if ($start->gte($end)) {
            $start = $end->copy()->subSeconds(90);
        }
        $span = max(1, $start->diffInSeconds($end));

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $t = $start->copy()->addSeconds((int) round($span * $i / max(1, $count - 1)));
            if ($t->gt($end)) {
                $t = $end->copy();
            }
            $out[] = ['timestamp' => $t->toIso8601String(), 'value' => $v];
        }

        return $out;
    }

    /**
     * Seed points in a short window immediately before the first real sample so charts do not
     * span the full query range with a flat line and a vertical stack at "now".
     *
     * @return list<array{timestamp: string, value: float}>
     */
    protected function buildDefaultPrefixPointsBeforeFirst(CarbonInterface $firstReadingAt, float $defaultVal, int $n): array
    {
        $n = max(1, min(self::MONITOR_DEFAULT_PREFIX_POINTS, $n));
        $leadOffsets = [24, 18, 12, 6];
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $sec = $leadOffsets[$i] ?? (6 * ($n - $i));
            $out[] = [
                'timestamp' => $firstReadingAt->copy()->subSeconds($sec)->toIso8601String(),
                'value' => $defaultVal,
            ];
        }

        usort($out, fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

        return $out;
    }

    /**
     * Build temperature time series for Monitor charts (last N hours, hourly buckets = last reading per hour or interpolate).
     *
     * @return list<array{timestamp: string, value: float}>
     */
    public function temperatureSeriesForMonitor(int $containerId, int $hours = 24, int $stepHours = 2): array
    {
        return $this->sensorSeriesForMonitor($containerId, SimulationStateDto::SENSOR_TEMPERATURE, $hours, $stepHours);
    }
}
