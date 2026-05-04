<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'type',
        'severity',
        'description',
        'container_id',
        'shipment_id',
        'insurance_policy_number',
        'reported_at',
        'resolved_at',
        'resolution_status',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
