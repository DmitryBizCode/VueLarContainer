<?php

namespace Tests\Feature;

use App\Events\UserMessageCreated;
use App\Listeners\SendNewMessageMail;
use App\Mail\InAppNotificationMail;
use App\Mail\NewUserMessageMail;
use App\Models\User;
use App\Models\UserMessage;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationChannelPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_patch_notification_channels(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'notification_email_enabled' => true,
            'notification_telegram_enabled' => true,
        ]);

        $this->actingAs($user)->patch(route('profile.notification-channels.update'), [
            'notification_email_enabled' => false,
            'notification_telegram_enabled' => true,
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertFalse($user->notification_email_enabled);
        $this->assertTrue($user->notification_telegram_enabled);
    }

    public function test_in_app_notification_not_emailed_when_user_disables_email_channel(): void
    {
        Mail::fake();
        config()->set('notifications.in_app_email_enabled', true);

        /** @var User $user */
        $user = User::factory()->create([
            'notification_email_enabled' => false,
        ]);

        app(NotificationService::class)->notifyUserInApp($user, 'info', 'Title', 'Body', null);

        Mail::assertNotSent(InAppNotificationMail::class);
    }

    public function test_new_message_mail_not_sent_when_user_disables_email_channel(): void
    {
        Mail::fake();
        config()->set('notifications.new_message_email_enabled', true);

        /** @var User $recipient */
        $recipient = User::factory()->create([
            'notification_email_enabled' => false,
        ]);
        /** @var User $sender */
        $sender = User::factory()->create();

        $message = UserMessage::factory()->create([
            'recipient_user_id' => $recipient->id,
            'sender_user_id' => $sender->id,
        ]);

        app(SendNewMessageMail::class)->handle(new UserMessageCreated($message));

        Mail::assertNotSent(NewUserMessageMail::class);
    }
}
