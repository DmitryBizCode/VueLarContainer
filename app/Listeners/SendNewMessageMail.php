<?php

namespace App\Listeners;

use App\Events\UserMessageCreated;
use App\Mail\NewUserMessageMail;
use App\Models\User;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNewMessageMail implements ShouldQueue
{
    public function __construct(
        private EmailNotificationService $email,
    ) {}

    public function handle(UserMessageCreated $event): void
    {
        if (! config('notifications.new_message_email_enabled', true)) {
            return;
        }

        $message = $event->message;
        $recipient = User::query()->find($message->recipient_user_id);
        if ($recipient === null || $recipient->email === null || $recipient->email === '') {
            return;
        }
        if (! $recipient->notification_email_enabled) {
            return;
        }

        $senderLabel = 'Operations team';
        if ($message->sender_user_id) {
            $sender = User::query()->find($message->sender_user_id);
            if ($sender) {
                $name = trim(implode(' ', array_filter([$sender->first_name, $sender->last_name])));
                $senderLabel = $name !== '' ? $name : ($sender->email ?? $senderLabel);
            }
        }

        $this->email->send(
            new NewUserMessageMail($message, $recipient, $senderLabel),
            $recipient->email,
        );
    }
}
