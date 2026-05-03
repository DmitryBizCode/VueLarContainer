<?php

namespace App\Listeners;

use App\Events\UserMessageCreated;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class CreateNewMessageInAppNotification
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function handle(UserMessageCreated $event): void
    {
        $message = $event->message;
        $recipient = User::query()->find($message->recipient_user_id);
        if ($recipient === null) {
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

        $this->notifications->notifyNewUserMessage($message, $recipient, $senderLabel);
    }
}
