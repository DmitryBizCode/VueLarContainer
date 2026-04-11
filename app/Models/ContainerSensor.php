<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerSensor extends Model
{
    protected $fillable = [
        'container_id',
        'sensor_type_id',
        'enabled',
        'config',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function sensorType(): BelongsTo
    {
        return $this->belongsTo(SensorType::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
