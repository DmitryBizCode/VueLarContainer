<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramBotClient
{
    /**
     * Bump when changing {@see defaultBotCommands()} so poll re-runs setMyCommands.
     */
    public const MY_COMMANDS_VERSION = 2;

    public function __construct(
        private readonly string $token,
        private readonly int $timeoutSeconds = 10,
    ) {}

    public static function myCommandsCacheKey(): string
    {
        return 'telegram:my_commands_registered_v'.self::MY_COMMANDS_VERSION;
    }

    /**
     * @return list<array{command: string, description: string}>
     */
    public static function defaultBotCommands(): array
    {
        return [
            ['command' => 'start', 'description' => 'Welcome, buttons & how to connect'],
            ['command' => 'help', 'description' => 'All commands & linking tips'],
            ['command' => 'status', 'description' => 'Linked? Notifications on?'],
            ['command' => 'link', 'description' => 'Link: /link YOURCODE'],
            ['command' => 'connect', 'description' => 'Same as /link'],
            ['command' => 'unlink', 'description' => 'Disconnect this chat'],
            ['command' => 'stop', 'description' => 'Same as /unlink'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function registerDefaultBotCommands(): array
    {
        return $this->call('setMyCommands', [
            'commands' => self::defaultBotCommands(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options  Extra Telegram fields: parse_mode (HTML|MarkdownV2), reply_markup, etc.
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ], $options);

        return $this->request()
            ->post($this->url('/sendMessage'), $payload)
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $method, array $payload = []): array
    {
        $method = ltrim($method, '/');

        return $this->request()
            ->post($this->url('/'.$method), $payload)
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false, array $extra = []): array
    {
        $payload = array_merge([
            'callback_query_id' => $callbackQueryId,
            'show_alert' => $showAlert,
        ], $extra);

        if ($text !== null && $text !== '') {
            $payload['text'] = $text;
        }

        return $this->call('answerCallbackQuery', $payload);
    }

    public function getUpdates(?int $offset = null, int $timeoutSeconds = 25, int $limit = 50): array
    {
        $payload = [
            'timeout' => $timeoutSeconds,
            'limit' => $limit,
        ];
        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        // Telegram long-poll keeps the connection open for `timeout` seconds.
        // Ensure our HTTP client timeout exceeds that value.
        return $this->request()
            ->timeout(max($this->timeoutSeconds, $timeoutSeconds + 5))
            ->post($this->url('/getUpdates'), $payload)
            ->throw()
            ->json();
    }

    public static function fromConfig(): self
    {
        return new self(
            token: (string) config('services.telegram.bot_token', ''),
            timeoutSeconds: (int) config('services.telegram.timeout', 10),
        );
    }

    private function request(): PendingRequest
    {
        return Http::asJson()
            ->timeout($this->timeoutSeconds)
            ->retry(2, 250, function ($exception) {
                // Retry on transport / 5xx, but not on 4xx.
                if ($exception instanceof RequestException) {
                    $code = $exception->response?->status();

                    return $code !== null && $code >= 500;
                }

                return true;
            })
            ->withHeaders([
                'User-Agent' => 'LogisticsDmitryBot/1.0',
            ]);
    }

    private function url(string $path): string
    {
        $token = trim($this->token);
        if ($token === '') {
            Log::error('telegram.missing_token');
        }

        return "https://api.telegram.org/bot{$token}{$path}";
    }
}
