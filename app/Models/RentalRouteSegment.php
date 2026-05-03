<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalRouteSegment extends Model
{
    protected $fillable = [
        'rental_id',
        'segment_order',
        'from_port_id',
        'to_port_id',
        'route_id',
        'vessel_id',
        'planned_departure_at',
        'planned_arrival_at',
        'travel_duration_hours',
        'waiting_time_before_hours',
        'waiting_time_after_hours',
        'status',
    ];

    protected $casts = [
        'planned_departure_at' => 'datetime',
        'planned_arrival_at' => 'datetime',
        'segment_order' => 'integer',
        'travel_duration_hours' => 'integer',
        'waiting_time_before_hours' => 'integer',
        'waiting_time_after_hours' => 'integer',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function fromPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'from_port_id');
    }

    public function toPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'to_port_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
