<?php

namespace Tests\Feature;

use App\Mail\InquirySubmittedAdminMail;
use App\Mail\InquirySubmittedUserMail;
use App\Models\Inquiry;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactInquiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submit_creates_inquiry_and_sends_user_mail(): void
    {
        Mail::fake();
        Config::set('notifications.admin_inbox', 'ops@example.com');

        $response = $this->post(route('contact.submit'), [
            'name' => 'Jane Client',
            'email' => 'jane@example.com',
            'subject' => 'Container Rental',
            'message' => 'We need 2x40ft from Rotterdam.',
        ]);

        $response->assertRedirect(route('contact'));
        $this->assertDatabaseHas('inquiries', [
            'email' => 'jane@example.com',
            'status' => 'new',
            'source' => 'website',
        ]);

        Mail::assertSent(InquirySubmittedUserMail::class);
        Mail::assertSent(InquirySubmittedAdminMail::class);
        $this->assertCount(1, Mail::sent(InquirySubmittedUserMail::class));
        $this->assertCount(1, Mail::sent(InquirySubmittedAdminMail::class));
    }

    public function test_contact_submit_creates_in_app_notification_for_logged_in_user(): void
    {
        Mail::fake();
        Config::set('notifications.admin_inbox', 'ops@example.com');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contact.submit'), [
            'name' => 'Jane Client',
            'email' => $user->email,
            'subject' => 'Technical Support',
            'message' => 'Question about IoT.',
        ])->assertRedirect(route('contact'));

        $inquiry = Inquiry::query()->where('email', $user->email)->first();
        $this->assertNotNull($inquiry);
        $this->assertSame((int) $user->id, (int) $inquiry->submitted_by_user_id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'request_submitted',
        ]);

        Mail::assertSent(InquirySubmittedUserMail::class);
        Mail::assertSent(InquirySubmittedAdminMail::class);
        $this->assertCount(1, Mail::sent(InquirySubmittedUserMail::class));
        $this->assertCount(1, Mail::sent(InquirySubmittedAdminMail::class));
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $note = Notification::query()->create([
            'user_id' => $b->id,
            'title' => 'Private',
            'message' => 'Secret',
            'type' => 'info',
            'is_read' => false,
        ]);

        $this->actingAs($a)
            ->patch(route('notifications.read', $note))
            ->assertNotFound();
    }

    public function test_notifications_json_endpoints_are_scoped_to_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Notification::query()->create([
            'user_id' => $b->id,
            'title' => 'B only',
            'message' => 'X',
            'type' => 'info',
            'is_read' => false,
        ]);

        $res = $this->actingAs($a)->getJson(route('notifications.index'));
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertSame([], $ids);

        $this->actingAs($a)->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_admin_message_creates_recipient_notification(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($admin)
            ->post(route('admin.users.messages.store', $client), [
                'subject' => 'Regarding your rental',
                'body' => 'Please upload signed documents.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_messages', [
            'recipient_user_id' => $client->id,
            'sender_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'new_message',
        ]);
    }
}
