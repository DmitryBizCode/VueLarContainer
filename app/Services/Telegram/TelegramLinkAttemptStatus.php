<?php

namespace App\Services\Telegram;

enum TelegramLinkAttemptStatus: string
{
    case Linked = 'linked';
    case AlreadyLinked = 'already_linked';
    case InvalidCode = 'invalid_code';
    case TelegramInUseByOtherUser = 'telegram_in_use';
}
