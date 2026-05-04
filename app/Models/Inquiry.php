<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    /** @use HasFactory<\Database\Factories\InquiryFactory> */
    use HasFactory;

    public const HANDLING_NEW = 'new';

    public const HANDLING_IN_PROGRESS = 'in_progress';

    public const HANDLING_CONTACTED = 'contacted';

    public const HANDLING_NO_CONTACT = 'no_contact';

    public const HANDLING_CLOSED = 'closed';

    public const HANDLING_REJECTED = 'rejected';

    public const HANDLING_SPAM = 'spam';

    public const HANDLING_CONVERTED = 'converted';

    /**
     * @return list<string>
     */
    public static function handlingStatusValues(): array
    {
        return [
            self::HANDLING_NEW,
            self::HANDLING_IN_PROGRESS,
            self::HANDLING_CONTACTED,
            self::HANDLING_NO_CONTACT,
            self::HANDLING_CLOSED,
            self::HANDLING_REJECTED,
            self::HANDLING_SPAM,
            self::HANDLING_CONVERTED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function handlingStatusLabels(): array
    {
        return [
            self::HANDLING_NEW => 'New',
            self::HANDLING_IN_PROGRESS => 'In progress',
            self::HANDLING_CONTACTED => 'Contacted',
            self::HANDLING_NO_CONTACT => 'No contact / unreachable',
            self::HANDLING_CLOSED => 'Closed',
            self::HANDLING_REJECTED => 'Rejected / declined',
            self::HANDLING_SPAM => 'Spam',
            self::HANDLING_CONVERTED => 'Converted',
        ];
    }

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'telegram_username',
        'subject',
        'message',
        'source',
        'handling_status',
        'admin_notes',
        'converted_user_id',
        'submitted_by_user_id',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
