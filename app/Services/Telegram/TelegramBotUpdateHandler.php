<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Models\UserTelegramLink;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class TelegramBotUpdateHandler
{
    private const AWAITING_CODE_TTL_SECONDS = 900;

    public const CACHE_AWAITING_PREFIX = 'telegram:awaiting_link_code:';

    public function __construct(
        private readonly TelegramBotClient $client,
        private readonly TelegramAccountLinkService $accountLinks,
    ) {}

    public function awaitingCodeCacheKey(int $chatId): string
    {
        return self::CACHE_AWAITING_PREFIX.$chatId;
    }

    /**
     * @param  array{id?: int, username?: string|null, first_name?: string|null, last_name?: string|null}|null  $from
     */
    public function handleIncomingText(int $chatId, string $text, ?array $from = null): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $text = $this->mapKeyboardLabelToCommand($text) ?? $text;

        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $parts[0] ?? '';
        $commandBase = strtolower(preg_replace('#@\w+$#', '', $first));

        $isCommand = str_starts_with($commandBase, '/');

        if (! $isCommand && Cache::has($this->awaitingCodeCacheKey($chatId))) {
            $this->processLinkAttempt($chatId, $text, $from);

            return;
        }

        if (in_array($commandBase, ['/stop', '/unlink'], true)) {
            $this->clearAwaitingCode($chatId);
            $this->unlinkChat($chatId);

            return;
        }

        if ($commandBase === '/start') {
            $this->clearAwaitingCode($chatId);
            $code = $parts[1] ?? null;
            if ($code !== null && $code !== '') {
                $this->processLinkAttempt($chatId, $code, $from);

                return;
            }
            $this->sendWelcome($chatId);

            return;
        }

        if ($commandBase === '/help') {
            $this->clearAwaitingCode($chatId);
            $this->sendHelp($chatId);

            return;
        }

        if ($commandBase === '/status') {
            $this->clearAwaitingCode($chatId);
            $this->sendStatus($chatId);

            return;
        }

        if (in_array($commandBase, ['/link', '/connect'], true)) {
            $code = $parts[1] ?? null;
            if ($code === null || $code === '') {
                Cache::put($this->awaitingCodeCacheKey($chatId), true, now()->addSeconds(self::AWAITING_CODE_TTL_SECONDS));
                $this->client->sendMessage($chatId, $this->linkAwaitingCodeText(), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->persistentReplyKeyboard(),
                ]);

                return;
            }
            $this->processLinkAttempt($chatId, $code, $from);

            return;
        }

        if (count($parts) === 1 && preg_match('/^[A-Z0-9]{8,24}$/i', $parts[0])) {
            $this->processLinkAttempt($chatId, $parts[0], $from);

            return;
        }

        $this->client->sendMessage($chatId, $this->unknownCommandText(), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    public function handleCallbackQuery(int $chatId, string $callbackQueryId, string $data): void
    {
        $data = trim($data);

        try {
            $this->client->answerCallbackQuery($callbackQueryId);
        } catch (Throwable) {
        }

        match ($data) {
            'h' => $this->sendHelp($chatId),
            's' => $this->sendStatus($chatId),
            'i' => $this->beginAwaitingLinkCode($chatId),
            'u' => $this->unlinkChat($chatId),
            'm' => $this->sendWelcome($chatId),
            default => $this->client->sendMessage($chatId, $this->unknownCommandText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]),
        };
    }

    private function beginAwaitingLinkCode(int $chatId): void
    {
        Cache::put($this->awaitingCodeCacheKey($chatId), true, now()->addSeconds(self::AWAITING_CODE_TTL_SECONDS));
        $this->client->sendMessage($chatId, $this->linkAwaitingCodeText(), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function clearAwaitingCode(int $chatId): void
    {
        Cache::forget($this->awaitingCodeCacheKey($chatId));
    }

    /**
     * @param  array{id?: int, username?: string|null, first_name?: string|null, last_name?: string|null}|null  $from
     */
    private function processLinkAttempt(int $chatId, string $code, ?array $from): void
    {
        $hadAwaiting = Cache::has($this->awaitingCodeCacheKey($chatId));
        $result = $this->accountLinks->tryLink($chatId, $from, $code);

        match ($result->status) {
            TelegramLinkAttemptStatus::Linked => $this->client->sendMessage($chatId, $this->linkSuccessText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]),
            TelegramLinkAttemptStatus::AlreadyLinked => $this->client->sendMessage($chatId, $this->alreadyLinkedText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]),
            TelegramLinkAttemptStatus::InvalidCode => $this->client->sendMessage($chatId, $this->invalidCodeText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]),
            TelegramLinkAttemptStatus::TelegramInUseByOtherUser => $this->client->sendMessage($chatId, $this->telegramInUseByOtherAccountText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]),
        };

        if ($result->status === TelegramLinkAttemptStatus::InvalidCode && $hadAwaiting) {
            Cache::put($this->awaitingCodeCacheKey($chatId), true, now()->addSeconds(self::AWAITING_CODE_TTL_SECONDS));
        } else {
            $this->clearAwaitingCode($chatId);
        }
    }

    private function mapKeyboardLabelToCommand(string $text): ?string
    {
        $t = trim($text);
        $map = [
            // Legacy Cyrillic labels (older keyboards)
            'Помощь' => '/help',
            '❓ Помощь' => '/help',
            'Проверить статус' => '/status',
            'Привязать аккаунт' => '/link',
            'Отключить уведомления' => '/unlink',
            'Допомога' => '/help',
            '❓ Допомога' => '/help',
            'Статус' => '/status',
            '📊 Статус' => '/status',
            'Прив\'язати акаунт' => '/link',
            'Вимкнути сповіщення' => '/unlink',
            // English (current keyboard)
            'Help' => '/help',
            '❓ Help' => '/help',
            '📋 Help' => '/help',
            '📖 Help' => '/help',
            'Status' => '/status',
            '📊 Status' => '/status',
            'Link account' => '/link',
            'ℹ️ Link' => '/link',
            'How to link' => '/link',
            'Disable notifications' => '/unlink',
            'Unlink' => '/unlink',
            '🔓 Unlink' => '/unlink',
        ];

        return $map[$t] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function persistentReplyKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => 'Link account'],
                    ['text' => 'Status'],
                ],
                [
                    ['text' => 'Disable notifications'],
                    ['text' => 'Help'],
                ],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function removeReplyKeyboard(): array
    {
        return [
            'remove_keyboard' => true,
        ];
    }

    private function unlinkChat(int $chatId): void
    {
        $this->accountLinks->unlinkChat($chatId);

        $this->client->sendMessage($chatId, $this->unlinkSuccessText(), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->removeReplyKeyboard(),
        ]);
    }

    private function sendWelcome(int $chatId): void
    {
        $app = e((string) config('app.name', 'App'));

        $body = <<<HTML
✨ <b>Welcome!</b>

<b>{$app}</b> — notifications in Telegram.

<b>How to connect</b>
1 · Account → notifications → <b>link code</b>
2 · Tap <b>«Link account»</b> and send the code here
3 · Or open the link from the site (“Open in Telegram”)

<b>Menu</b> — buttons below the message field.
HTML;

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function sendHelp(int $chatId): void
    {
        $app = e((string) config('app.name', 'App'));

        $body = <<<HTML
<b>{$app}</b> — commands

• <code>/start</code> — main menu
• <code>/link</code> — link (then the code from the site)
• <code>/status</code> — link status
• <code>/unlink</code> — unlink this Telegram chat
• <code>/help</code> — this help

The code is single-use and expires — generate a new one in your profile if needed.
HTML;

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function sendStatus(int $chatId): void
    {
        $link = UserTelegramLink::query()
            ->where('telegram_chat_id', $chatId)
            ->where('status', UserTelegramLink::STATUS_ACTIVE)
            ->first();

        if ($link === null) {
            $this->client->sendMessage($chatId, $this->statusNotLinkedText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]);

            return;
        }

        $user = User::query()->find($link->user_id);
        $telegramOn = $user?->notification_telegram_enabled ?? false;
        $hint = $telegramOn
            ? '✅ Linked · Telegram notifications are <b>enabled</b> in your profile'
            : '✅ Linked · enable the Telegram channel in your profile to receive pushes';

        $body = "<b>Status</b>\n{$hint}";

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function linkAwaitingCodeText(): string
    {
        return <<<'HTML'
🔑 <b>Account linking</b>

Send the <b>link code</b> from your account (notifications section) in <b>one message</b>.

The code expires. If it has expired, generate a new one on the site.
HTML;
    }

    private function unknownCommandText(): string
    {
        return <<<'HTML'
I did not understand. Send the <b>link code</b> or tap <b>«Help»</b>.
HTML;
    }

    private function invalidCodeText(): string
    {
        return <<<'HTML'
❌ Invalid or expired code. Create a new one in your account.
HTML;
    }

    private function linkSuccessText(): string
    {
        return <<<'HTML'
🎉 <b>Account connected</b>

Notifications for this Telegram are <b>active</b> (if the channel is enabled in your site profile).

You will receive important events: requests, messages, and system alerts.
HTML;
    }

    private function alreadyLinkedText(): string
    {
        return <<<'HTML'
✅ This Telegram is already linked to your site account. Details updated.
HTML;
    }

    private function telegramInUseByOtherAccountText(): string
    {
        return <<<'HTML'
⛔️ This Telegram is already linked to a <b>different</b> site account.

One Telegram account cannot be linked to two profiles. Sign in as the correct user on the site or unlink Telegram from the profile where it is connected.
HTML;
    }

    private function unlinkSuccessText(): string
    {
        return <<<'HTML'
🔓 <b>Notifications disabled</b> for this chat.

You can connect again anytime from your account on the site.
HTML;
    }

    private function statusNotLinkedText(): string
    {
        return <<<'HTML'
Not linked yet. Get a code in your account (notifications) and tap «Link account».
HTML;
    }
}
