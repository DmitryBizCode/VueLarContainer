<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    protected $fillable = [
        'user_id',
        'container_id',
        'start_date',
        'end_date',
        'actual_return_date',
        'price',
        'status',
        'payment_status',
        'contract_pdf',
        'description',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'actual_return_date' => 'datetime',
        'price' => 'decimal:2',
    ];
}
