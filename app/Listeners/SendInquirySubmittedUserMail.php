<?php

namespace App\Listeners;

use App\Events\InquirySubmitted;
use App\Mail\InquirySubmittedUserMail;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendInquirySubmittedUserMail implements ShouldQueue
{
    public function __construct(
        private EmailNotificationService $email,
    ) {}

    public function handle(InquirySubmitted $event): void
    {
        $email = $event->inquiry->email;
        if ($email === null || $email === '') {
            return;
        }

        $name = $event->submitter
            ? trim(implode(' ', array_filter([$event->submitter->first_name, $event->submitter->last_name])))
            : null;

        $this->email->send(
            new InquirySubmittedUserMail($event->inquiry, $name ?: null),
            $email,
        );
    }
}
