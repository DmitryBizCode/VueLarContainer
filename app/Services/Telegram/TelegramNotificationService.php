<?php

namespace App\Services\Telegram;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class TelegramNotificationService
{
    public function __construct(
        private readonly TelegramBotClient $client,
    ) {}

    public function sendForNotification(User $user, Notification $notification): bool
    {
        if (! config('notifications.telegram_enabled', true)) {
            return false;
        }

        $chatId = $user->telegram_chat_id;
        if (! $chatId) {
            return false;
        }

        $dedupeKey = 'tg_sent:notification:'.$notification->id;
        if (Cache::has($dedupeKey)) {
            return true;
        }

        $text = $this->format($notification);

        try {
            $this->client->sendMessage((int) $chatId, $text, [
                'parse_mode' => 'HTML',
            ]);

            Cache::put($dedupeKey, true, now()->addDays(7));

            return true;
        } catch (Throwable $e) {
            Log::error('telegram.send_failed', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function format(Notification $n): string
    {
        $app = e((string) config('app.name', 'App'));

        $title = trim((string) $n->title);
        $message = trim((string) $n->message);
        $rawType = strtolower(trim((string) $n->type));

        $typeEmoji = match ($rawType) {
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'success' => '✅',
            'error', 'critical', 'danger' => '❗️',
            default => $rawType !== '' ? '📌' : '',
        };

        $blocks = [
            "🔔 <b>{$app}</b>",
        ];

        $bodyChunks = [];
        if ($title !== '') {
            $bodyChunks[] = '<b>'.e($title).'</b>';
        }
        if ($message !== '') {
            $bodyChunks[] = e($message);
        }

        if ($bodyChunks !== []) {
            $blocks[] = '';
            $blocks[] = implode("\n\n", $bodyChunks);
        }

        if ($rawType !== '' && $typeEmoji !== '') {
            $label = e(Str::title(str_replace('_', ' ', $rawType)));
            $blocks[] = '';
            $blocks[] = '· · ·';
            $blocks[] = "<i>{$typeEmoji} {$label}</i>";
        }

        return implode("\n", $blocks);
    }
}
