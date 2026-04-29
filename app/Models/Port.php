<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Port extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'country_id',
        'name',
        'city',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function originRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'origin_port_id');
    }

    public function destinationRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'destination_port_id');
    }
}
