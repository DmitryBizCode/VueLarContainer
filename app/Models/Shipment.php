<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'vessel_id',
        'route_id',
        'departure_date',
        'arrival_date',
        'actual_departure_date',
        'actual_arrival_date',
        'tracking_number',
        'status',
    ];

    protected $casts = [
        'departure_date' => 'datetime',
        'arrival_date' => 'datetime',
        'actual_departure_date' => 'datetime',
        'actual_arrival_date' => 'datetime',
    ];
}
