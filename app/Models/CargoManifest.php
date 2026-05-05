<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoManifest extends Model
{
    protected $fillable = [
        'rental_id',
        'name',
        'description',
        'hs_code',
        'weight_kg',
        'volume_m3',
        'is_dangerous',
        'declared_value',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'volume_m3' => 'decimal:2',
            'declared_value' => 'decimal:2',
            'is_dangerous' => 'boolean',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
