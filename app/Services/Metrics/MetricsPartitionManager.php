<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetricsPartitionManager
{
    /** Sentinel for `rental_id` NULL → LIST partition FOR VALUES IN (-1). */
    public const NULL_PARTITION_KEY = -1;

    public function partitionKey(?int $rentalId): int
    {
        return $rentalId === null ? self::NULL_PARTITION_KEY : $rentalId;
    }

    public function partitionTableName(int $partitionKey): string
    {
        return $partitionKey === self::NULL_PARTITION_KEY
            ? 'metrics_p_null'
            : 'metrics_p_rental_'.$partitionKey;
    }

    /**
     * Ensures a PostgreSQL child partition exists for this rental (or the NULL bucket).
     * No-op on non-PostgreSQL drivers (e.g. SQLite tests).
     */
    public function ensurePartitionForRentalId(?int $rentalId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! $this->metricsIsListPartitioned()) {
            return;
        }

        $key = $this->partitionKey($rentalId);
        $name = $this->partitionTableName($key);

        if ($this->partitionRelationExists($name)) {
            return;
        }

        try {
            if ($key === self::NULL_PARTITION_KEY) {
                DB::unprepared('CREATE TABLE metrics_p_null PARTITION OF metrics FOR VALUES IN (-1);');
            } else {
                DB::unprepared('CREATE TABLE "'.$name.'" PARTITION OF metrics FOR VALUES IN ('.$key.');');
            }
        } catch (Throwable $e) {
            if ($this->isDuplicateRelationError($e) && $this->partitionRelationExists($name)) {
                return;
            }
            Log::error('metrics.partition.create_failed', [
                'partition' => $name,
                'key' => $key,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function partitionExistsForRentalId(?int $rentalId): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return true;
        }

        return $this->partitionRelationExists($this->partitionTableName($this->partitionKey($rentalId)));
    }

    public function isMetricsListPartitioned(): bool
    {
        return $this->metricsIsListPartitioned();
    }

    public function dropPartitionForRentalId(int $rentalId): bool
    {
        if (DB::getDriverName() !== 'pgsql' || ! $this->metricsIsListPartitioned()) {
            return false;
        }

        $name = $this->partitionTableName($rentalId);
        if (! $this->partitionRelationExists($name)) {
            return false;
        }

        DB::unprepared('DROP TABLE IF EXISTS "'.$name.'"');

        return true;
    }

    protected function metricsIsListPartitioned(): bool
    {
        $row = DB::selectOne("
            SELECT c.relkind = 'p' AS partitioned
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema() AND c.relname = 'metrics'
        ");

        return $row && (bool) $row->partitioned;
    }

    protected function partitionRelationExists(string $relName): bool
    {
        $row = DB::selectOne('
            SELECT 1
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = current_schema() AND c.relname = ?
            LIMIT 1
        ', [$relName]);

        return $row !== null;
    }

    protected function isDuplicateRelationError(Throwable $e): bool
    {
        $sqlState = method_exists($e, 'getCode') ? (string) $e->getCode() : '';

        return $sqlState === '42P07'
            || str_contains($e->getMessage(), 'already exists');
    }
}
