<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'path',
        'method',
        'ip_address',
        'user_agent',
        'country_code',
        'region',
        'city',
        'timezone',
        'gmt_offset_minutes',
        'browser',
        'browser_version',
        'device_type',
        'platform',
        'accept_language',
        'referer',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
