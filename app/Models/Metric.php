<?php

namespace App\Models;

use App\Services\Metrics\MetricsPartitionManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Metric extends Model
{
    /**
     * Present on PostgreSQL when `metrics` is LIST-partitioned (set on create from rental_id).
     */
    protected $hidden = [
        'metrics_partition_key',
    ];

    protected $fillable = [
        'container_id',
        'rental_id',
        'type',
        'value',
        'unit',
        'meta',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'recorded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Metric $model) {
            if ($model->getConnection()->getDriverName() !== 'pgsql') {
                return;
            }

            if (! Schema::hasColumn($model->getTable(), 'metrics_partition_key')) {
                return;
            }

            $rid = $model->rental_id;
            $rid = is_numeric($rid) ? (int) $rid : null;
            $model->setAttribute(
                'metrics_partition_key',
                $rid === null ? MetricsPartitionManager::NULL_PARTITION_KEY : $rid,
            );
        });
    }

    /**
     * IoT sensor key (stored as `type` column).
     */
    public function getSensorKeyAttribute(): string
    {
        return (string) $this->type;
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function scopeForContainer($query, int $containerId)
    {
        return $query->where('container_id', $containerId);
    }

    /**
     * @param  string|array<int, string>  $types  Sensor / metric keys (same as former sensor_key).
     */
    public function scopeForType($query, string|array $types)
    {
        if (is_array($types)) {
            return $query->whereIn('type', $types);
        }

        return $query->where('type', $types);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('recorded_at', [$from, $to]);
    }
}
