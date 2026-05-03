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
        private readonly TelegramAccountLinkService $accountLinks,
    ) {}

    public function sendForNotification(User $user, Notification $notification): bool
    {
        if (! config('notifications.telegram_enabled', true)) {
            return false;
        }

        $links = $user->activeTelegramLinks()->get();
        if ($links->isEmpty()) {
            return false;
        }

        $text = $this->format($notification);
        $anySent = false;

        foreach ($links as $link) {
            $dedupeKey = 'tg_sent:notification:'.$notification->id.':chat:'.$link->telegram_chat_id;
            if (Cache::has($dedupeKey)) {
                $anySent = true;

                continue;
            }

            try {
                $this->client->sendMessage((int) $link->telegram_chat_id, $text, [
                    'parse_mode' => 'HTML',
                ]);
                Cache::put($dedupeKey, true, now()->addDays(7));
                $this->accountLinks->touchActivity($link);
                $anySent = true;
            } catch (Throwable $e) {
                Log::error('telegram.send_failed', [
                    'user_id' => $user->id,
                    'telegram_link_id' => $link->id,
                    'notification_id' => $notification->id,
                    'message' => $e->getMessage(),
                ]);
                if (TelegramBotClient::isUnrecoverableChatDeliveryError($e)) {
                    $this->accountLinks->markLinkDisabledForDeliveryFailure($link, $e->getMessage());
                }
            }
        }

        return $anySent;
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

        if ($n->created_at !== null) {
            $blocks[] = '';
            $blocks[] = '<i>🕐 '.e($n->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i')).'</i>';
        }

        $actionUrl = trim((string) $n->action_url);
        if ($actionUrl !== '') {
            $blocks[] = '';
            $blocks[] = '🔗 <a href="'.e($actionUrl).'">'.e('Open in account').'</a>';
        }

        return implode("\n", $blocks);
    }
}
