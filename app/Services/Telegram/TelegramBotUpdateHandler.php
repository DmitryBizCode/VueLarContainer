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
            'Помощь' => '/help',
            '❓ Помощь' => '/help',
            'Help' => '/help',
            '❓ Help' => '/help',
            '📋 Help' => '/help',
            '📖 Help' => '/help',
            'Проверить статус' => '/status',
            'Status' => '/status',
            '📊 Status' => '/status',
            'Привязать аккаунт' => '/link',
            'ℹ️ Link' => '/link',
            'How to link' => '/link',
            'Отключить уведомления' => '/unlink',
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
                    ['text' => 'Привязать аккаунт'],
                    ['text' => 'Проверить статус'],
                ],
                [
                    ['text' => 'Отключить уведомления'],
                    ['text' => 'Помощь'],
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
✨ <b>Добро пожаловать!</b>

<b>{$app}</b> — уведомления в Telegram.

<b>Как подключить</b>
1 · Личный кабинет → уведомления → <b>код привязки</b>
2 · Нажмите <b>«Привязать аккаунт»</b> и отправьте код сюда
3 · Или откройте ссылку с сайта (кнопка «Открыть в Telegram»)

<b>Меню</b> — кнопки под полем ввода.
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
<b>{$app}</b> — команды

• <code>/start</code> — главное меню
• <code>/link</code> — привязка (затем код из кабинета)
• <code>/status</code> — статус привязки
• <code>/unlink</code> — отключить этот Telegram
• <code>/help</code> — эта подсказка

Код одноразовый и с ограниченным сроком — при необходимости создайте новый в профиле.
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
            ? '✅ Привязано · доставка уведомлений <b>включена</b> в профиле'
            : '✅ Привязано · включите канал Telegram в профиле, чтобы получать push';

        $body = "<b>Статус</b>\n{$hint}";

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function linkAwaitingCodeText(): string
    {
        return <<<'HTML'
🔑 <b>Привязка аккаунта</b>

Отправьте <b>одним сообщением</b> код из личного кабинета (раздел уведомлений).

Срок действия кода ограничен. Если истёк — сгенерируйте новый на сайте.
HTML;
    }

    private function unknownCommandText(): string
    {
        return <<<'HTML'
Не понял запрос. Отправьте <b>код привязки</b> или нажмите <b>«Помощь»</b>.
HTML;
    }

    private function invalidCodeText(): string
    {
        return <<<'HTML'
❌ Код недействителен или истёк. Создайте новый в личном кабинете.
HTML;
    }

    private function linkSuccessText(): string
    {
        return <<<'HTML'
🎉 <b>Аккаунт подключён</b>

Уведомления для этого Telegram <b>активированы</b> (если канал включён в профиле на сайте).

Вы будете получать важные события: заявки, сообщения и системные оповещения.
HTML;
    }

    private function alreadyLinkedText(): string
    {
        return <<<'HTML'
✅ Этот Telegram уже привязан к вашему аккаунту на сайте. Данные обновлены.
HTML;
    }

    private function telegramInUseByOtherAccountText(): string
    {
        return <<<'HTML'
⛔️ Этот Telegram уже привязан к <b>другому</b> аккаунту на сайте.

Один аккаунт Telegram нельзя связать с двумя разными профилями. Войдите на сайте под нужным пользователем или отвяжите Telegram в том профиле, где он подключён.
HTML;
    }

    private function unlinkSuccessText(): string
    {
        return <<<'HTML'
🔓 <b>Уведомления отключены</b> для этого чата.

Подключить снова можно в любой момент через личный кабинет.
HTML;
    }

    private function statusNotLinkedText(): string
    {
        return <<<'HTML'
Ещё не привязано. Получите код в личном кабинете и нажмите «Привязать аккаунт».
HTML;
    }
}
