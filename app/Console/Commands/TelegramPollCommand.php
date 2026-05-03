<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramAccountLinkService;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramBotUpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Run one poll cycle and exit}';

    protected $description = 'Poll Telegram bot updates and process messages';

    public function handle(): int
    {
        if (trim((string) config('services.telegram.bot_token', '')) === '') {
            $this->error('TELEGRAM_BOT_TOKEN is empty. Set it in .env before running telegram:poll.');

            return self::FAILURE;
        }

        $client = TelegramBotClient::fromConfig();
        $this->ensureBotCommandsRegistered($client);

        $handler = new TelegramBotUpdateHandler($client, app(TelegramAccountLinkService::class));
        $pollInterval = max(1, (int) env('TELEGRAM_POLL_INTERVAL_SECONDS', 2));

        do {
            $this->pollOnce($client, $handler);

            if ($this->option('once')) {
                break;
            }

            sleep($pollInterval);
        } while (true);

        return self::SUCCESS;
    }

    private function ensureBotCommandsRegistered(TelegramBotClient $client): void
    {
        $key = TelegramBotClient::myCommandsCacheKey();
        if (Cache::has($key)) {
            return;
        }

        try {
            $client->registerDefaultBotCommands();
            Cache::forever($key, true);
        } catch (Throwable $e) {
            $this->warn('Telegram setMyCommands (Menu) failed: '.$e->getMessage());
        }
    }

    private function pollOnce(TelegramBotClient $client, TelegramBotUpdateHandler $handler): void
    {
        $offsetKey = 'telegram:poll_offset';
        $offset = Cache::get($offsetKey);
        $offset = is_numeric($offset) ? (int) $offset : null;

        try {
            $json = $client->getUpdates($offset, timeoutSeconds: 25, limit: 50);
        } catch (Throwable $e) {
            $this->error('getUpdates failed: '.$e->getMessage());

            return;
        }

        $updates = $json['result'] ?? [];
        if (! is_array($updates) || $updates === []) {
            return;
        }

        $maxUpdateId = null;
        foreach ($updates as $update) {
            $updateId = (int) ($update['update_id'] ?? 0);
            $maxUpdateId = $maxUpdateId === null ? $updateId : max($maxUpdateId, $updateId);

            $callback = $update['callback_query'] ?? null;
            if (is_array($callback)) {
                $cqId = (string) ($callback['id'] ?? '');
                $data = (string) ($callback['data'] ?? '');
                $msg = $callback['message'] ?? null;
                $chatId = is_array($msg) ? ($msg['chat']['id'] ?? null) : null;
                if ($cqId !== '' && is_numeric($chatId)) {
                    $handler->handleCallbackQuery((int) $chatId, $cqId, $data);
                }

                continue;
            }

            $message = $update['message'] ?? null;
            if (! is_array($message)) {
                continue;
            }

            $chatId = $message['chat']['id'] ?? null;
            $text = (string) ($message['text'] ?? '');
            if (! is_numeric($chatId) || trim($text) === '') {
                continue;
            }

            $from = isset($message['from']) && is_array($message['from']) ? $message['from'] : null;
            $handler->handleIncomingText((int) $chatId, $text, $from);
        }

        if ($maxUpdateId !== null) {
            Cache::put($offsetKey, $maxUpdateId + 1, now()->addDays(30));
        }
    }
}
