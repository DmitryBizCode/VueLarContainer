<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'category',
        'is_optional',
        'telemetry_keys',
        'sort_order',
    ];

    protected $casts = [
        'is_optional' => 'boolean',
        'telemetry_keys' => 'array',
    ];

    public function containers(): BelongsToMany
    {
        return $this->belongsToMany(Container::class, 'container_sensors')
            ->withPivot(['enabled', 'config', 'sort_order'])
            ->withTimestamps();
    }

    public function containerSensors(): HasMany
    {
        return $this->hasMany(ContainerSensor::class);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_optional', true);
    }

    public function scopeMandatory($query)
    {
        return $query->where('is_optional', false);
    }
}
