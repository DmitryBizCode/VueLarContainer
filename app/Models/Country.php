<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /** @use HasFactory<\Database\Factories\CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'iso_code',
        'phone_code',
        'interest_tax',
    ];

    protected $casts = [
        'interest_tax' => 'decimal:2',
    ];
}
