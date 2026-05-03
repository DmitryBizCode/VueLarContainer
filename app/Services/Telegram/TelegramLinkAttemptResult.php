<?php

namespace App\Services\Telegram;

use App\Models\UserTelegramLink;

final readonly class TelegramLinkAttemptResult
{
    public function __construct(
        public TelegramLinkAttemptStatus $status,
        public ?UserTelegramLink $link = null,
    ) {}
}
