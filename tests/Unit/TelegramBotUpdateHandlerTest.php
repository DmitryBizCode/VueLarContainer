<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserTelegramLink;
use App\Services\Telegram\TelegramAccountLinkService;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramBotUpdateHandler;
use App\Services\Telegram\TelegramLinkCodeService;
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
        return new TelegramBotUpdateHandler(
            new TelegramBotClient('test-token', 10),
            app(TelegramAccountLinkService::class),
        );
    }

    public function test_plain_code_links_account(): void
    {
        $user = User::factory()->create();
        $plain = app(TelegramLinkCodeService::class)->issueForUser($user)['plain'];

        $this->makeHandler()->handleIncomingText(424242, $plain, ['id' => 1, 'username' => 'u']);

        $link = UserTelegramLink::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($link);
        $this->assertSame(424242, (int) $link->telegram_chat_id);
        $this->assertNotNull(
            \App\Models\TelegramLinkCode::query()
                ->where('user_id', $user->id)
                ->whereNotNull('consumed_at')
                ->first()
        );

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage'));
    }

    public function test_start_with_payload_links(): void
    {
        $user = User::factory()->create();
        $plain = app(TelegramLinkCodeService::class)->issueForUser($user)['plain'];

        $this->makeHandler()->handleIncomingText(1001, '/start '.$plain, null);

        $this->assertSame(1001, (int) UserTelegramLink::query()->where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_link_command_links(): void
    {
        $user = User::factory()->create();
        $plain = app(TelegramLinkCodeService::class)->issueForUser($user)['plain'];

        $this->makeHandler()->handleIncomingText(2002, '/link '.$plain, null);

        $this->assertSame(2002, (int) UserTelegramLink::query()->where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_connect_alias_links(): void
    {
        $user = User::factory()->create();
        $plain = app(TelegramLinkCodeService::class)->issueForUser($user)['plain'];

        $this->makeHandler()->handleIncomingText(3003, '/connect '.$plain, null);

        $this->assertSame(3003, (int) UserTelegramLink::query()->where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_invalid_code_does_not_link(): void
    {
        $user = User::factory()->create();
        app(TelegramLinkCodeService::class)->issueForUser($user);

        $this->makeHandler()->handleIncomingText(5005, 'NOTREALCODEHERE', null);

        $this->assertNull(UserTelegramLink::query()->where('user_id', $user->id)->first());
    }

    public function test_unlink_removes_link_row(): void
    {
        $user = User::factory()->create();
        UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 777001,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        $this->makeHandler()->handleIncomingText(777001, '/unlink');

        $this->assertNull(UserTelegramLink::query()->where('telegram_chat_id', 777001)->first());
    }

    public function test_status_shows_linked_when_chat_matches(): void
    {
        $user = User::factory()->create([
            'notification_telegram_enabled' => true,
        ]);
        UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 888002,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        $this->makeHandler()->handleIncomingText(888002, '/status');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, 'Статус') && str_contains($text, 'Привязано');
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

            return str_contains($text, 'привязано') || str_contains($text, 'Ещё не');
        });
    }

    public function test_keyboard_help_label_triggers_help(): void
    {
        $this->makeHandler()->handleIncomingText(1, 'Помощь');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, '/status') && str_contains($text, '/link');
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

            return str_contains($text, '/status') || str_contains($text, '/link');
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

    public function test_link_without_code_prompts_for_code(): void
    {
        $this->makeHandler()->handleIncomingText(1, '/link');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $body = $request->data();
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, 'код') || str_contains($text, 'Привязк');
        });
    }

    public function test_telegram_cannot_be_rebound_to_another_site_user(): void
    {
        $first = User::factory()->create([
            'email' => 'first@example.com',
        ]);
        UserTelegramLink::query()->create([
            'user_id' => $first->id,
            'telegram_chat_id' => 600600,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        $second = User::factory()->create([
            'email' => 'second@example.com',
        ]);
        $plain = app(TelegramLinkCodeService::class)->issueForUser($second)['plain'];

        $this->makeHandler()->handleIncomingText(600600, $plain, null);

        $this->assertSame($first->id, (int) UserTelegramLink::query()->where('telegram_chat_id', 600600)->value('user_id'));
        $this->assertNull(UserTelegramLink::query()->where('user_id', $second->id)->first());
    }

    public function test_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $plain = app(TelegramLinkCodeService::class)->issueForUser($user)['plain'];

        $this->makeHandler()->handleIncomingText(111, $plain, null);
        $this->makeHandler()->handleIncomingText(222, $plain, null);

        $this->assertSame(111, (int) UserTelegramLink::query()->where('user_id', $user->id)->value('telegram_chat_id'));
        $this->assertNull(UserTelegramLink::query()->where('telegram_chat_id', 222)->first());
    }
}
