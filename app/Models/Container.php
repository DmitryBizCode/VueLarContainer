<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Container extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'serial_number',
        'type',
        'width',
        'length',
        'height',
        'max_weight',
        'manufacture_date',
        'photo',
        'iot_active',
        'current_status',
        'owner_id',
        'current_port_id',
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'length' => 'decimal:2',
        'height' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'manufacture_date' => 'date',
        'iot_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function currentPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'current_port_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function simulationSnapshot()
    {
        return $this->hasOne(ContainerSimulationSnapshot::class);
    }

    public function containerSensors(): HasMany
    {
        return $this->hasMany(ContainerSensor::class)->orderBy('sort_order');
    }

    public function sensorTypes(): BelongsToMany
    {
        return $this->belongsToMany(SensorType::class, 'container_sensors')
            ->withPivot(['enabled', 'config', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function iotAuditChain(): HasMany
    {
        return $this->hasMany(IotAuditChain::class)->orderByDesc('sequence');
    }
}
