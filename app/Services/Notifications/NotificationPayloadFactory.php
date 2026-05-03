<?php

namespace App\Services\Notifications;

use App\Models\Inquiry;
use App\Models\UserMessage;

class NotificationPayloadFactory
{
    public function inquirySubmitted(Inquiry $inquiry, string $dashboardUrl): array
    {
        $subject = $inquiry->subject ?? 'Your inquiry';

        return [
            'type' => 'request_submitted',
            'title' => 'Request received',
            'message' => 'We saved your message: '.$subject.' (#'.$inquiry->id.'). Our team will respond soon.',
            'action_url' => $dashboardUrl,
        ];
    }

    public function newUserMessage(UserMessage $message, string $senderLabel): array
    {
        $preview = \Illuminate\Support\Str::limit(strip_tags($message->body), 120);
        $sub = $message->subject ? ' · '.$message->subject : '';

        return [
            'type' => 'new_message',
            'title' => 'New message',
            'message' => 'From '.$senderLabel.$sub.': '.$preview,
            'action_url' => route('messages.show', ['message' => $message->id], true),
        ];
    }
}
