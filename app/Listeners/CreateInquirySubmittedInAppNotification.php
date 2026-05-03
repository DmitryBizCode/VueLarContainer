<?php

namespace App\Listeners;

use App\Events\InquirySubmitted;
use App\Services\Notifications\NotificationService;

class CreateInquirySubmittedInAppNotification
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function handle(InquirySubmitted $event): void
    {
        if ($event->submitter === null) {
            return;
        }

        $this->notifications->notifyInquirySubmitted($event->submitter, $event->inquiry);
    }
}
