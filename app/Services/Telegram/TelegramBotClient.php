<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class TelegramBotClient
{
    /**
     * Bump when changing {@see defaultBotCommands()} so poll re-runs setMyCommands.
     */
    public const MY_COMMANDS_VERSION = 4;

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
            ['command' => 'start', 'description' => 'Main menu and welcome'],
            ['command' => 'link', 'description' => 'Link account (code from the site)'],
            ['command' => 'status', 'description' => 'Check link status'],
            ['command' => 'unlink', 'description' => 'Unlink this Telegram chat'],
            ['command' => 'help', 'description' => 'Command help'],
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

    /**
     * True when retrying the same chat will not help (blocked bot, deleted chat, etc.).
     */
    public static function isUnrecoverableChatDeliveryError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        if ($e instanceof RequestException) {
            $status = $e->response?->status();
            if ($status === 403) {
                return true;
            }
            $body = (string) $e->response?->body();
            $message .= ' '.strtolower($body);
        }

        return Str::contains($message, [
            'bot was blocked',
            'blocked by the user',
            'user is deactivated',
            'chat not found',
            'chat_id is empty',
            'peer_id_invalid',
            'have no rights to send',
            'need administrator rights',
        ]);
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
