<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContainerSimulationSnapshot extends Model
{
    protected $fillable = [
        'container_id',
        'rental_id',
        'sensor_state',
        'actuators',
        'last_tick_at',
    ];

    protected function casts(): array
    {
        return [
            'sensor_state' => 'array',
            'actuators' => 'array',
            'last_tick_at' => 'datetime',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
