<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTelegramLink;
use App\Services\Telegram\TelegramLinkCodeService;
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

        $hash = TelegramLinkCodeService::hashPlain($code);
        $this->assertDatabaseHas('telegram_link_codes', [
            'user_id' => $user->id,
            'code_hash' => $hash,
        ]);
    }

    public function test_user_can_remove_one_telegram_link(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $link = UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 999111222,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        $this->actingAs($user)->deleteJson(route('telegram.links.destroy', ['link' => $link->id]))->assertOk();

        $this->assertNull(UserTelegramLink::query()->find($link->id));
    }

    public function test_user_cannot_remove_foreign_telegram_link(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $link = UserTelegramLink::query()->create([
            'user_id' => $owner->id,
            'telegram_chat_id' => 888777666,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        $this->actingAs($other)->deleteJson(route('telegram.links.destroy', ['link' => $link->id]))->assertForbidden();
    }

    public function test_notification_pushes_to_telegram_for_each_linked_chat(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        config()->set('notifications.telegram_enabled', true);
        config()->set('services.telegram.bot_token', 'test-token');

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        foreach ([123456789, 987654321] as $chatId) {
            UserTelegramLink::query()->create([
                'user_id' => $user->id,
                'telegram_chat_id' => $chatId,
                'status' => UserTelegramLink::STATUS_ACTIVE,
                'linked_at' => now(),
            ]);
        }

        app(\App\Services\Notifications\NotificationService::class)->notifyUserInApp(
            $user,
            'info',
            'Hello',
            'World',
            'https://example.test/x'
        );

        Http::assertSentCount(2);
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
            'notification_telegram_enabled' => false,
        ]);
        UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 123456789,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
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

    public function test_permanent_telegram_error_marks_link_disabled(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 403),
        ]);

        config()->set('notifications.telegram_enabled', true);
        config()->set('services.telegram.bot_token', 'test-token');

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $link = UserTelegramLink::query()->create([
            'user_id' => $user->id,
            'telegram_chat_id' => 555444333,
            'status' => UserTelegramLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        app(\App\Services\Telegram\TelegramNotificationService::class)->sendForNotification(
            $user,
            \App\Models\Notification::query()->create([
                'user_id' => $user->id,
                'title' => 'T',
                'message' => 'M',
                'type' => 'info',
                'action_url' => null,
                'is_read' => false,
            ])
        );

        $link->refresh();
        $this->assertSame(UserTelegramLink::STATUS_DISABLED, $link->status);
    }
}
