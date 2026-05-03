<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInquiryHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function createInquiry(): Inquiry
    {
        return Inquiry::query()->create([
            'name' => 'Lead User',
            'email' => 'lead@example.com',
            'phone_number' => null,
            'telegram_username' => null,
            'subject' => 'Quote',
            'message' => 'Need containers.',
            'source' => 'website',
            'status' => 'new',
            'handling_status' => Inquiry::HANDLING_NEW,
            'admin_notes' => null,
            'converted_user_id' => null,
            'submitted_by_user_id' => null,
        ]);
    }

    public function test_admin_can_update_inquiry_handling(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inquiry = $this->createInquiry();

        $this->actingAs($admin)
            ->patch(route('admin.inquiries.update', $inquiry), [
                'handling_status' => Inquiry::HANDLING_NO_CONTACT,
                'admin_notes' => 'Left voicemail',
            ])
            ->assertRedirect();

        $inquiry->refresh();
        $this->assertSame(Inquiry::HANDLING_NO_CONTACT, $inquiry->handling_status);
        $this->assertSame('Left voicemail', $inquiry->admin_notes);
    }

    public function test_non_admin_cannot_update_inquiry_handling(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $inquiry = $this->createInquiry();

        $this->actingAs($client)
            ->patch(route('admin.inquiries.update', $inquiry), [
                'handling_status' => Inquiry::HANDLING_CLOSED,
            ])
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_inquiry_update_validates_handling_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $inquiry = $this->createInquiry();

        $this->actingAs($admin)
            ->patch(route('admin.inquiries.update', $inquiry), [
                'handling_status' => 'not-a-real-status',
            ])
            ->assertSessionHasErrors('handling_status');
    }

    public function test_admin_can_view_inquiries_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createInquiry();

        $this->actingAs($admin)
            ->get(route('admin.inquiries.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Inquiries/Index')
                ->has('inquiries.data', 1));
    }
}
