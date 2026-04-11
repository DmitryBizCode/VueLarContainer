<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IoT time-series: one row per reading; `type` is the sensor key (e.g. temperature_c).
     *
     * PostgreSQL: LIST-partitioned by `metrics_partition_key` (app sets COALESCE(rental_id, -1)).
     * Fresh DB starts with partition `metrics_p_null` only; per-rental partitions are created at runtime.
     * SQLite (tests): flat table without `metrics_partition_key`.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE SEQUENCE metrics_id_seq;
SQL);
            DB::unprepared(<<<'SQL'
CREATE TABLE metrics (
    id bigint NOT NULL DEFAULT nextval('metrics_id_seq'::regclass),
    container_id bigint NOT NULL,
    rental_id bigint,
    type character varying(64) NOT NULL,
    value numeric(12,4) NOT NULL DEFAULT 0,
    unit character varying(100),
    meta json,
    recorded_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    metrics_partition_key bigint NOT NULL,
    PRIMARY KEY (id, metrics_partition_key),
    CONSTRAINT metrics_container_id_foreign FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE,
    CONSTRAINT metrics_rental_id_foreign FOREIGN KEY (rental_id) REFERENCES rentals (id) ON DELETE SET NULL
) PARTITION BY LIST (metrics_partition_key);
SQL);
            DB::unprepared(<<<'SQL'
ALTER SEQUENCE metrics_id_seq OWNED BY metrics.id;
SQL);
            DB::unprepared(<<<'SQL'
CREATE TABLE metrics_p_null PARTITION OF metrics FOR VALUES IN (-1);
SQL);
            DB::unprepared(<<<'SQL'
CREATE INDEX metrics_container_recorded_idx ON metrics (container_id, recorded_at);
CREATE INDEX metrics_type_recorded_idx ON metrics (type, recorded_at);
CREATE INDEX metrics_rental_recorded_idx ON metrics (rental_id, recorded_at);
SQL);

            return;
        }

        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 64);
            $table->decimal('value', 12, 4)->default(0.0000);
            $table->string('unit', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestampTz('recorded_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['container_id', 'recorded_at'], 'metrics_container_recorded_idx');
            $table->index(['type', 'recorded_at'], 'metrics_type_recorded_idx');
            $table->index(['rental_id', 'recorded_at'], 'metrics_rental_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP SEQUENCE IF EXISTS metrics_id_seq CASCADE');
        }
    }
};
