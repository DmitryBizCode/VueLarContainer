<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramBotUpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotUpdateHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);
    }

    private function makeHandler(): TelegramBotUpdateHandler
    {
        return new TelegramBotUpdateHandler(new TelegramBotClient('test-token', 10));
    }

    public function test_plain_code_links_account(): void
    {
        $user = User::factory()->create([
            'telegram_link_code' => 'LINKCODE1',
            'telegram_link_code_expires_at' => now()->addHour(),
        ]);

        $this->makeHandler()->handleIncomingText(424242, 'linkcode1');

        $user->refresh();
        $this->assertSame(424242, (int) $user->telegram_chat_id);
        $this->assertNull($user->telegram_link_code);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage'));
    }

    public function test_start_with_payload_links(): void
    {
        $user = User::factory()->create([
            'telegram_link_code' => 'STARTIT9',
            'telegram_link_code_expires_at' => now()->addHour(),
        ]);

        $this->makeHandler()->handleIncomingText(1001, '/start STARTIT9');

        $user->refresh();
        $this->assertSame(1001, (int) $user->telegram_chat_id);
    }

    public function test_link_command_links(): void
    {
        $user = User::factory()->create([
            'telegram_link_code' => 'CMDLINK1',
            'telegram_link_code_expires_at' => now()->addHour(),
        ]);

        $this->makeHandler()->handleIncomingText(2002, '/link cmdlink1');

        $user->refresh();
        $this->assertSame(2002, (int) $user->telegram_chat_id);
    }

    public function test_connect_alias_links(): void
    {
        $user = User::factory()->create([
            'telegram_link_code' => 'ALIAS01',
            'telegram_link_code_expires_at' => now()->addHour(),
        ]);

        $this->makeHandler()->handleIncomingText(3003, '/connect ALIAS01');

        $user->refresh();
        $this->assertSame(3003, (int) $user->telegram_chat_id);
    }

    public function test_invalid_code_does_not_link(): void
    {
        $user = User::factory()->create([
            'telegram_link_code' => 'VALID001',
            'telegram_link_code_expires_at' => now()->addHour(),
        ]);

        $this->makeHandler()->handleIncomingText(5005, 'NOTREAL1');

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
        $this->assertSame('VALID001', $user->telegram_link_code);
    }

    public function test_unlink_clears_telegram_chat(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 777001,
        ]);

        $this->makeHandler()->handleIncomingText(777001, '/unlink');

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
    }

    public function test_status_shows_linked_when_chat_matches(): void
    {
        User::factory()->create([
            'telegram_chat_id' => 888002,
            'notification_telegram_enabled' => true,
        ]);

        $this->makeHandler()->handleIncomingText(888002, '/status');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, 'Status') && str_contains($text, 'Linked');
        });
    }

    public function test_status_shows_not_linked(): void
    {
        $this->makeHandler()->handleIncomingText(999888, '/status');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, 'Not linked') || str_contains($text, 'linked yet');
        });
    }

    public function test_keyboard_help_label_triggers_help(): void
    {
        $this->makeHandler()->handleIncomingText(1, '📋 Help');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, '/status') || str_contains($text, 'shortcuts');
        });
    }

    public function test_callback_query_triggers_help(): void
    {
        $this->makeHandler()->handleCallbackQuery(42, 'cq-1', 'h');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'answerCallbackQuery')) {
                return false;
            }

            return true;
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, 'shortcuts') || str_contains($text, '/status');
        });
    }

    public function test_help_sends_commands_message(): void
    {
        $this->makeHandler()->handleIncomingText(1, '/help');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, '/status') && str_contains($text, '/link');
        });
    }

    public function test_link_without_code_sends_usage(): void
    {
        $this->makeHandler()->handleIncomingText(1, '/link');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, '/link') && str_contains($text, 'code');
        });
    }

    public function test_reassigns_chat_when_linking_second_account(): void
    {
        $first = User::factory()->create([
            'telegram_chat_id' => 600600,
            'email' => 'first@example.com',
        ]);
        $second = User::factory()->create([
            'telegram_link_code' => 'SECOND1',
            'telegram_link_code_expires_at' => now()->addHour(),
            'email' => 'second@example.com',
        ]);

        $this->makeHandler()->handleIncomingText(600600, 'SECOND1');

        $first->refresh();
        $second->refresh();
        $this->assertNull($first->telegram_chat_id);
        $this->assertSame(600600, (int) $second->telegram_chat_id);
    }
}
