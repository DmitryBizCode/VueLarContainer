<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vessel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'imo_number',
        'capacity_teu',
        'status',
        'last_inspection_date',
        'current_port_id',
        'berth_busy_until',
        'out_of_service_until',
    ];

    protected $casts = [
        'capacity_teu' => 'integer',
        'last_inspection_date' => 'date',
        'berth_busy_until' => 'datetime',
        'out_of_service_until' => 'datetime',
    ];

    public function currentPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'current_port_id');
    }
}
