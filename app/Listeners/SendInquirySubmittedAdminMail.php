<?php

namespace App\Listeners;

use App\Events\InquirySubmitted;
use App\Mail\InquirySubmittedAdminMail;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendInquirySubmittedAdminMail implements ShouldQueue
{
    public function __construct(
        private EmailNotificationService $email,
    ) {}

    public function handle(InquirySubmitted $event): void
    {
        if (config('notifications.inquiry_admin_mail_requires_authenticated_submitter', false) && $event->submitter === null) {
            return;
        }

        $raw = config('notifications.admin_inbox');
        if ($raw === null || $raw === '') {
            return;
        }

        $recipients = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($recipients === []) {
            return;
        }

        $this->email->send(new InquirySubmittedAdminMail($event->inquiry), $recipients);
    }
}
