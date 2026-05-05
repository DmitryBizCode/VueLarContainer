<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $fillable = [
        'container_id',
        'maintenance_date',
        'maintenance_type',
        'description',
        'cost',
        'technician_name',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_date' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }
}
