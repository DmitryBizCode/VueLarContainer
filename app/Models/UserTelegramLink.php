<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTelegramLink extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_username',
        'first_name',
        'last_name',
        'status',
        'linked_at',
        'last_activity_at',
        'last_error_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<UserTelegramLink>  $query
     * @return \Illuminate\Database\Eloquent\Builder<UserTelegramLink>
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
