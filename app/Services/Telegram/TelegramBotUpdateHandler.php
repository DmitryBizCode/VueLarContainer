<?php

namespace App\Services\Telegram;

use App\Models\User;
use Throwable;

final class TelegramBotUpdateHandler
{
    /** @var list<string> */
    private const KEYBOARD_HELP = ['Help', '❓ Help', '📋 Help'];

    /** @var list<string> */
    private const KEYBOARD_STATUS = ['Status', '📊 Status'];

    /** @var list<string> */
    private const KEYBOARD_HOW = ['How to link', 'ℹ️ Link'];

    /** @var list<string> */
    private const KEYBOARD_UNLINK = ['Unlink', '🔓 Unlink'];

    public function __construct(
        private readonly TelegramBotClient $client,
    ) {}

    public function handleIncomingText(int $chatId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $text = $this->mapKeyboardLabelToCommand($text) ?? $text;

        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $parts[0] ?? '';
        $commandBase = strtolower(preg_replace('#@\w+$#', '', $first));

        if (in_array($commandBase, ['/stop', '/unlink'], true)) {
            $this->unlinkChat($chatId);

            return;
        }

        if ($commandBase === '/linkinfo') {
            $this->sendHowToLink($chatId);

            return;
        }

        if ($commandBase === '/start') {
            $code = $parts[1] ?? null;
            if ($code !== null && $code !== '') {
                $this->tryLinkCode($chatId, $code);

                return;
            }
            $this->sendWelcome($chatId);

            return;
        }

        if ($commandBase === '/help') {
            $this->sendHelp($chatId);

            return;
        }

        if ($commandBase === '/status') {
            $this->sendStatus($chatId);

            return;
        }

        if (in_array($commandBase, ['/link', '/connect'], true)) {
            $code = $parts[1] ?? null;
            if ($code === null || $code === '') {
                $this->client->sendMessage($chatId, $this->linkCommandUsage(), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->persistentReplyKeyboard(),
                ]);

                return;
            }
            $this->tryLinkCode($chatId, $code);

            return;
        }

        if (count($parts) === 1 && preg_match('/^[A-Z0-9]{6,12}$/i', $parts[0])) {
            $this->tryLinkCode($chatId, $parts[0]);

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
            // Ignore expired / duplicate callback ack
        }

        match ($data) {
            'h' => $this->sendHelp($chatId),
            's' => $this->sendStatus($chatId),
            'i' => $this->sendHowToLink($chatId),
            'm' => $this->sendWelcome($chatId),
            default => $this->client->sendMessage($chatId, $this->unknownCommandText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]),
        };
    }

    private function mapKeyboardLabelToCommand(string $text): ?string
    {
        $t = trim($text);
        if (in_array($t, self::KEYBOARD_HELP, true)) {
            return '/help';
        }
        if (in_array($t, self::KEYBOARD_STATUS, true)) {
            return '/status';
        }
        if (in_array($t, self::KEYBOARD_HOW, true)) {
            return '/linkinfo';
        }
        if (in_array($t, self::KEYBOARD_UNLINK, true)) {
            return '/unlink';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function persistentReplyKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '❓ Help'],
                    ['text' => '📊 Status'],
                ],
                [
                    ['text' => 'ℹ️ Link'],
                ],
                [
                    ['text' => '🔓 Unlink'],
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
        $user = User::query()->where('telegram_chat_id', $chatId)->first();
        if ($user) {
            $user->forceFill([
                'telegram_chat_id' => null,
                'telegram_link_code' => null,
                'telegram_link_code_expires_at' => null,
            ])->save();
        }

        $this->client->sendMessage($chatId, $this->unlinkSuccessText(), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->removeReplyKeyboard(),
        ]);
    }

    private function tryLinkCode(int $chatId, string $code): void
    {
        $code = strtoupper(trim($code));

        $user = User::query()
            ->where('telegram_link_code', $code)
            ->whereNotNull('telegram_link_code_expires_at')
            ->where('telegram_link_code_expires_at', '>=', now())
            ->first();

        if (! $user) {
            $this->client->sendMessage($chatId, $this->invalidCodeText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]);

            return;
        }

        User::query()
            ->where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->update([
                'telegram_chat_id' => null,
                'telegram_link_code' => null,
                'telegram_link_code_expires_at' => null,
            ]);

        $user->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_link_code' => null,
            'telegram_link_code_expires_at' => null,
        ])->save();

        $this->client->sendMessage($chatId, $this->linkSuccessText(), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function sendWelcome(int $chatId): void
    {
        $app = e((string) config('app.name', 'App'));

        $body = <<<HTML
<b>{$app}</b> · Telegram notifications

<b>Link</b> (same steps as the blue card on the site):
1 · Profile → Notifications → code
2 · Paste the code <u>here</u> or <b>Open in Telegram</b> on the web

<b>Menu</b> ↔ next to the input · use the <b>button rows</b> below
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
<b>{$app}</b> — shortcuts

• <code>/status</code> — linked / delivery on?
• <code>/link CODE</code> — or paste <code>CODE</code> alone
• <code>/unlink</code> — disconnect

More: open <b>Menu</b> beside the input field.
HTML;

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function sendHowToLink(int $chatId): void
    {
        $body = <<<'HTML'
<b>Link in 3 steps</b> (same card as on the site)

1 · Website → <b>Profile</b> → <b>Notifications</b>
2 · <b>Get connection code</b>
3 · <b>Open in Telegram</b> or paste the code here

New code if this one expired.
HTML;

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function sendStatus(int $chatId): void
    {
        $user = User::query()->where('telegram_chat_id', $chatId)->first();

        if (! $user) {
            $this->client->sendMessage($chatId, $this->statusNotLinkedText(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->persistentReplyKeyboard(),
            ]);

            return;
        }

        $telegramOn = $user->notification_telegram_enabled;
        $hint = $telegramOn
            ? '✅ Linked · delivery <b>on</b>'
            : '✅ Linked · turn delivery <b>on</b> in Profile → Notifications';

        $body = "<b>Status</b>\n{$hint}";

        $this->client->sendMessage($chatId, $body, [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->persistentReplyKeyboard(),
        ]);
    }

    private function linkCommandUsage(): string
    {
        return <<<'HTML'
Use: <code>/link YOURCODE</code>
or send <code>YOURCODE</code> alone.
HTML;
    }

    private function unknownCommandText(): string
    {
        return <<<'HTML'
Paste your <b>code</b> from the website, or tap <b>Help</b>.
HTML;
    }

    private function invalidCodeText(): string
    {
        return <<<'HTML'
Code invalid or expired — generate a new one under <b>Notifications</b>.
HTML;
    }

    private function linkSuccessText(): string
    {
        return <<<'HTML'
✅ <b>Linked.</b> Enable Telegram in Notifications to get pushes.
HTML;
    }

    private function unlinkSuccessText(): string
    {
        return <<<'HTML'
🔓 <b>Unlinked.</b> Reconnect anytime from the website.
HTML;
    }

    private function statusNotLinkedText(): string
    {
        return <<<'HTML'
Not linked yet — get a code under <b>Profile → Notifications</b>, then paste it here.
HTML;
    }
}
