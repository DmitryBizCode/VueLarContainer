<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'origin_port_id',
        'destination_port_id',
        'estimated_days',
        'distance',
        'sea_path',
        'route_status',
    ];

    protected $casts = [
        'estimated_days' => 'integer',
        'distance' => 'float',
        'sea_path' => 'array',
    ];

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
