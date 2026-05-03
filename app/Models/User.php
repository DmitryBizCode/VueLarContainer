<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'email',
        'password',
        'phone_number',
        'address',
        'photo',
        'account_status',
        'role',
        'country_id',
        'commission_rate',
        'bonus_type',
        'bonus_value',
        'notification_email_enabled',
        'notification_telegram_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'commission_rate' => 'decimal:4',
            'bonus_value' => 'decimal:2',
            'notification_email_enabled' => 'boolean',
            'notification_telegram_enabled' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function panelNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(UserMessage::class, 'recipient_user_id');
    }

    public function telegramLinks(): HasMany
    {
        return $this->hasMany(UserTelegramLink::class);
    }

    public function telegramLinkCodes(): HasMany
    {
        return $this->hasMany(TelegramLinkCode::class);
    }

    public function activeTelegramLinks(): HasMany
    {
        return $this->telegramLinks()->active();
    }
}
