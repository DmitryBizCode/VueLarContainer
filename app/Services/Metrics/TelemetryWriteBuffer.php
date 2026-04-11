<?php

namespace App\Services\Metrics;

use App\Models\Rental;
use App\Support\MetricsSampleAggregator;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class TelemetryWriteBuffer
{
    public function __construct(
        protected MetricsPartitionManager $partitions,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function pushMany(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('metrics')) {
            return;
        }

        if (! config('metrics_buffer.enabled')) {
            $this->insertDirectly($rows);

            return;
        }

        try {
            $redis = Redis::connection(config('metrics_buffer.redis_connection'));
            $key = (string) config('metrics_buffer.list_key');
            foreach ($rows as $row) {
                $redis->rPush($key, json_encode($this->normalizeRowForJson($row), JSON_THROW_ON_ERROR));
            }
            $maxLen = (int) config('metrics_buffer.max_list_length');
            if ($maxLen > 0) {
                $redis->lTrim($key, -$maxLen, -1);
            }
        } catch (Throwable $e) {
            Log::warning('metrics.buffer.redis_push_failed_falling_back_to_db', [
                'message' => $e->getMessage(),
            ]);
            try {
                $this->insertDirectly($rows);
            } catch (Throwable $e2) {
                Log::error('metrics.buffer.direct_insert_after_redis_failed', [
                    'message' => $e2->getMessage(),
                ]);
            }
        }
    }

    /**
     * Drain buffered raw samples from Redis, aggregate per sensor (median vs discrete any-on), INSERT rows.
     *
     * @return int Number of aggregate metric rows inserted
     */
    public function flushFromRedis(): int
    {
        if (! Schema::hasTable('metrics') || ! config('metrics_buffer.enabled')) {
            return 0;
        }

        if ((bool) config('metrics_buffer.flush_atomic_rename', true)) {
            return $this->flushFromRedisAtomicRename();
        }

        if (! (bool) config('metrics_buffer.flush_use_lock', false)) {
            return $this->flushFromRedisSequentialPop();
        }

        try {
            $redis = Redis::connection(config('metrics_buffer.redis_connection'));
        } catch (Throwable) {
            return 0;
        }

        $lockKey = (string) config('metrics_buffer.flush_lock_key', 'metrics:telemetry_buffer_flush');
        $ttl = max(30, (int) config('metrics_buffer.flush_lock_ttl_seconds', 120));
        $wait = max(1, (int) config('metrics_buffer.flush_lock_wait_seconds', 10));
        $token = bin2hex(random_bytes(16));
        $deadline = microtime(true) + $wait;
        $acquired = false;

        while (microtime(true) < $deadline) {
            $ok = $redis->set($lockKey, $token, 'EX', $ttl, 'NX');
            if ($ok !== false && $ok !== null) {
                $acquired = true;
                break;
            }
            usleep(100_000);
        }

        if (! $acquired) {
            return 0;
        }

        $unlockScript = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
  return redis.call("del", KEYS[1])
else
  return 0
end
LUA;

        try {
            return $this->flushFromRedisSequentialPop();
        } finally {
            try {
                $redis->eval($unlockScript, 1, $lockKey, $token);
            } catch (Throwable $e) {
                Log::warning('metrics.buffer.flush_lock_release_failed', ['message' => $e->getMessage()]);
            }
        }
    }

    /**
     * Atomically move the whole buffer list aside, then read it — only one consumer gets each backlog snapshot.
     * New rPush calls create a fresh list key while we process the snapshot (no interleaved lPop with other flushers).
     */
    protected function flushFromRedisAtomicRename(): int
    {
        try {
            $redis = Redis::connection(config('metrics_buffer.redis_connection'));
        } catch (Throwable) {
            return 0;
        }

        $key = (string) config('metrics_buffer.list_key');

        if ((int) $redis->exists($key) < 1) {
            return 0;
        }

        $staging = $key.':draining:'.str_replace('-', '', Str::uuid()->toString());

        try {
            $redis->rename($key, $staging);
        } catch (Throwable $e) {
            Log::debug('metrics.buffer.atomic_rename_skipped', [
                'message' => $e->getMessage(),
                'list_key' => $key,
            ]);

            return 0;
        }

        try {
            $blobs = $redis->lRange($staging, 0, -1);
            if (! is_array($blobs)) {
                $blobs = [];
            }
        } finally {
            try {
                $redis->del($staging);
            } catch (Throwable $e) {
                Log::warning('metrics.buffer.staging_del_failed', ['message' => $e->getMessage()]);
            }
        }

        return $this->persistAggregatesFromRawBlobs($blobs);
    }

    /**
     * Legacy drain: sequential LPOP (two flushers can still interleave — prefer {@see flushFromRedisAtomicRename}).
     *
     * @return int Number of aggregate metric rows inserted
     */
    public function flushFromRedisSequentialPop(): int
    {
        if (! Schema::hasTable('metrics') || ! config('metrics_buffer.enabled')) {
            return 0;
        }

        try {
            $redis = Redis::connection(config('metrics_buffer.redis_connection'));
        } catch (Throwable) {
            return 0;
        }

        $key = (string) config('metrics_buffer.list_key');
        $maxDrain = max(1000, (int) config('metrics_buffer.flush_max_raw_rows', 250_000));
        $blobs = [];

        for ($i = 0; $i < $maxDrain; $i++) {
            $pop = $redis->lPop($key);
            if ($pop === null || $pop === false) {
                break;
            }
            $blobs[] = $pop;
        }

        return $this->persistAggregatesFromRawBlobs($blobs);
    }

    /**
     * @param  list<mixed>  $blobs
     */
    protected function persistAggregatesFromRawBlobs(array $blobs): int
    {
        $rawRows = [];
        foreach ($blobs as $pop) {
            try {
                $rawRows[] = json_decode((string) $pop, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                Log::warning('metrics.buffer.bad_json_skipped', ['message' => $e->getMessage()]);
            }
        }

        if ($rawRows === []) {
            return 0;
        }

        $flushAt = Carbon::now();
        $aggregates = MetricsSampleAggregator::aggregateToMedianRows($rawRows, $flushAt);

        if ($aggregates === []) {
            return 0;
        }

        $this->insertDirectly($aggregates);

        return count($aggregates);
    }

    /**
     * @deprecated Use {@see flushFromRedis} (atomic rename). Kept for any external callers/tests.
     */
    public function flushFromRedisUnlocked(): int
    {
        return $this->flushFromRedisSequentialPop();
    }

    /**
     * For PostgreSQL LIST-partitioned `metrics`, every INSERT must include `metrics_partition_key`
     * equal to {@see MetricsPartitionManager::partitionKey()}: `rental_id` or -1 when null.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function withPartitionKeyColumn(array $rows): array
    {
        if ($rows === [] || DB::getDriverName() !== 'pgsql' || ! $this->partitions->isMetricsListPartitioned()) {
            return $rows;
        }

        return array_map(function (array $row) {
            $rid = array_key_exists('rental_id', $row) ? $row['rental_id'] : null;
            $rid = is_numeric($rid) ? (int) $rid : null;
            $row['metrics_partition_key'] = $this->partitions->partitionKey($rid);

            return $row;
        }, $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertDirectly(array $rows): void
    {
        if ($rows === [] || ! Schema::hasTable('metrics')) {
            return;
        }

        $rows = $this->withPartitionKeyColumn($rows);

        $byRental = [];
        foreach ($rows as $row) {
            $rid = array_key_exists('rental_id', $row) ? $row['rental_id'] : null;
            $rid = is_numeric($rid) ? (int) $rid : null;
            $partitionKey = $this->partitions->partitionKey($rid);
            $byRental[$partitionKey][] = $row;
        }

        foreach ($byRental as $partitionKey => $group) {
            $rentalIdForEnsure = $partitionKey === MetricsPartitionManager::NULL_PARTITION_KEY
                ? null
                : $partitionKey;
            $this->partitions->ensurePartitionForRentalId($rentalIdForEnsure);

            foreach (array_chunk($group, 250) as $chunk) {
                DB::table('metrics')->insert($chunk);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRowForJson(array $row): array
    {
        foreach (['recorded_at', 'created_at', 'updated_at'] as $k) {
            if (isset($row[$k]) && $row[$k] instanceof DateTimeInterface) {
                // Sub-second precision so multiple ~10s ticks in the same wall second stay distinct
                // for peek/merge, chart dedupe, and frontend chart keys.
                $row[$k] = Carbon::instance($row[$k])->format('Y-m-d\TH:i:s.uP');
            }
        }

        return $row;
    }

    /**
     * Non-destructive read of the Redis list tail, grouped by `type`, scoped like monitor metrics.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function peekGroupedSamplesForScope(int $containerId, ?int $rentalId, ?int $lesseeUserId, ?int $maxTail = null): array
    {
        if (! config('metrics_buffer.enabled')) {
            return [];
        }

        $maxTail ??= (int) config('metrics_buffer.peek_max_tail', 8000);

        try {
            $redis = Redis::connection(config('metrics_buffer.redis_connection'));
        } catch (Throwable) {
            return [];
        }

        $key = (string) config('metrics_buffer.list_key');
        $len = (int) $redis->lLen($key);
        if ($len < 1) {
            return [];
        }

        $start = max(0, $len - max(1, $maxTail));
        $blobs = $redis->lRange($key, $start, -1);
        if (! is_array($blobs)) {
            return [];
        }

        $byType = [];
        foreach ($blobs as $blob) {
            try {
                $row = json_decode((string) $blob, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            if (! $this->bufferRowMatchesMonitorScope($row, $containerId, $rentalId, $lesseeUserId)) {
                continue;
            }
            $type = (string) ($row['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $byType[$type][] = $row;
        }

        return $byType;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function bufferRowMatchesMonitorScope(array $row, int $containerId, ?int $rentalId, ?int $lesseeUserId): bool
    {
        if ((int) ($row['container_id'] ?? 0) !== $containerId) {
            return false;
        }

        if ($rentalId === null) {
            return true;
        }

        $rid = isset($row['rental_id']) && is_numeric($row['rental_id']) ? (int) $row['rental_id'] : null;

        if ($lesseeUserId !== null) {
            $rentalIds = Rental::query()
                ->where('container_id', $containerId)
                ->where('user_id', $lesseeUserId)
                ->pluck('id')
                ->all();
            if ($rentalIds === []) {
                return $rid === null || $rid === $rentalId;
            }

            return $rid === null || in_array($rid, $rentalIds, true);
        }

        return $rid === null || $rid === $rentalId;
    }
}
