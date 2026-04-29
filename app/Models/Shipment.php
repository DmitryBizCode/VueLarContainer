<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'vessel_id',
        'route_id',
        'leg_sequence',
        'departure_date',
        'arrival_date',
        'actual_departure_date',
        'actual_arrival_date',
        'port_operations_until',
        'tracking_number',
        'status',
    ];

    protected $casts = [
        'leg_sequence' => 'integer',
        'departure_date' => 'datetime',
        'arrival_date' => 'datetime',
        'actual_departure_date' => 'datetime',
        'actual_arrival_date' => 'datetime',
        'port_operations_until' => 'datetime',
    ];

    public function vessel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function route(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }
}
