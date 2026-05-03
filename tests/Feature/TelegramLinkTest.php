<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_generate_telegram_link_code(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        /** @var \Illuminate\Contracts\Auth\Authenticatable $authUser */
        $authUser = $user;

        $res = $this->actingAs($authUser)->postJson(route('telegram.link-code'));
        $res->assertOk();

        $code = $res->json('code');
        $this->assertIsString($code);
        $this->assertNotSame('', $code);

        $user->refresh();
        $this->assertSame($code, $user->telegram_link_code);
        $this->assertNotNull($user->telegram_link_code_expires_at);
    }

    public function test_notification_pushes_to_telegram_once_when_linked(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        config()->set('notifications.telegram_enabled', true);
        config()->set('services.telegram.bot_token', 'test-token');

        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'telegram_chat_id' => 123456789,
        ]);

        app(\App\Services\Notifications\NotificationService::class)->notifyUserInApp(
            $user,
            'info',
            'Hello',
            'World',
            'https://example.test/x'
        );

        Http::assertSentCount(1);

        // Creating again should trigger another notification (new row), therefore another send.
        // But sending the same notification twice is prevented by tg_sent:notification:{id}.
    }

    public function test_notification_not_sent_to_telegram_when_user_disables_telegram_channel(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        config()->set('notifications.telegram_enabled', true);
        config()->set('services.telegram.bot_token', 'test-token');

        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'telegram_chat_id' => 123456789,
            'notification_telegram_enabled' => false,
        ]);

        app(\App\Services\Notifications\NotificationService::class)->notifyUserInApp(
            $user,
            'info',
            'Hello',
            'World',
            null,
        );

        Http::assertSentCount(0);
    }
}
