<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IotAuditChain extends Model
{
    protected $table = 'iot_audit_chain';

    protected $fillable = [
        'container_id',
        'rental_id',
        'user_id',
        'event_type',
        'payload',
        'sequence',
        'prev_hash',
        'row_hash',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
