<?php

namespace Tests\Feature;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramPollRegistersMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_telegram_poll_once_registers_bot_commands_when_cache_empty(): void
    {
        config()->set('services.telegram.bot_token', 'test-token-abc');

        $setMyCommandsHit = false;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$setMyCommandsHit) {
            $url = $request->url();
            if (str_contains($url, 'setMyCommands')) {
                $setMyCommandsHit = true;

                return Http::response(['ok' => true, 'result' => true], 200);
            }
            if (str_contains($url, 'getUpdates')) {
                return Http::response(['ok' => true, 'result' => []], 200);
            }

            return Http::response(['ok' => false], 400);
        });

        $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();

        $this->assertTrue($setMyCommandsHit);
        $this->assertTrue(Cache::has(TelegramBotClient::myCommandsCacheKey()));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if (! str_contains($request->url(), 'setMyCommands')) {
                return false;
            }
            $body = $request->data();

            return ($body['commands'] ?? null) === TelegramBotClient::defaultBotCommands();
        });
    }

    public function test_telegram_poll_does_not_repeat_set_my_commands_when_cached(): void
    {
        config()->set('services.telegram.bot_token', 'test-token-abc');
        Cache::forever(TelegramBotClient::myCommandsCacheKey(), true);

        $setMyCommandsCount = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$setMyCommandsCount) {
            $url = $request->url();
            if (str_contains($url, 'setMyCommands')) {
                $setMyCommandsCount++;

                return Http::response(['ok' => true, 'result' => true], 200);
            }
            if (str_contains($url, 'getUpdates')) {
                return Http::response(['ok' => true, 'result' => []], 200);
            }

            return Http::response(['ok' => false], 400);
        });

        $this->artisan('telegram:poll', ['--once' => true])->assertSuccessful();

        $this->assertSame(0, $setMyCommandsCount);
    }
}
