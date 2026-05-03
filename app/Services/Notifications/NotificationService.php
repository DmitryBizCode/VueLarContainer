<?php

namespace App\Services\Notifications;

use App\Mail\InAppNotificationMail;
use App\Models\Inquiry;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserMessage;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramNotificationService;
use Illuminate\Mail\Mailable;

class NotificationService
{
    public function __construct(
        private InAppNotificationService $inApp,
        private EmailNotificationService $email,
        private NotificationPayloadFactory $payloads,
    ) {}

    public function notifyUserInApp(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
    ): void {
        $row = $this->inApp->create((int) $user->id, $type, $title, $message, $actionUrl);
        $this->maybeEmailInAppNotification($user, $row);
        $this->maybeTelegramInAppNotification($user, $row);
    }

    public function notifyInquirySubmitted(User $user, Inquiry $inquiry): void
    {
        $payload = $this->payloads->inquirySubmitted($inquiry, url('/dashboard'));
        $row = $this->inApp->create(
            (int) $user->id,
            $payload['type'],
            $payload['title'],
            $payload['message'],
            $payload['action_url'],
        );
        $this->maybeEmailInAppNotification($user, $row);
        $this->maybeTelegramInAppNotification($user, $row);
    }

    public function notifyNewUserMessage(UserMessage $message, User $recipient, string $senderLabel): void
    {
        $payload = $this->payloads->newUserMessage($message, $senderLabel);
        $row = $this->inApp->create(
            (int) $recipient->id,
            $payload['type'],
            $payload['title'],
            $payload['message'],
            $payload['action_url'],
        );
        $this->maybeEmailInAppNotification($recipient, $row);
        $this->maybeTelegramInAppNotification($recipient, $row);
    }

    public function sendMail(Mailable $mailable, string|array $to): bool
    {
        return $this->email->send($mailable, $to);
    }

    public function unreadCount(User $user): int
    {
        return $this->inApp->unreadCount($user);
    }

    public function inApp(): InAppNotificationService
    {
        return $this->inApp;
    }

    private function maybeEmailInAppNotification(User $user, ?Notification $row): void
    {
        if (! config('notifications.in_app_email_enabled', true)) {
            return;
        }
        if ($row === null) {
            return;
        }
        // Contact form already sends dedicated emails (admin + lead confirmation).
        // Avoid sending a second email for the same event.
        if ($row->type === 'request_submitted') {
            return;
        }
        if ($user->email === null || $user->email === '') {
            return;
        }
        if (! $user->notification_email_enabled) {
            return;
        }

        $this->email->send(new InAppNotificationMail($user, $row), $user->email);
    }

    private function maybeTelegramInAppNotification(User $user, ?Notification $row): void
    {
        if ($row === null) {
            return;
        }
        if (! config('notifications.telegram_enabled', false)) {
            return;
        }
        if (! $user->telegram_chat_id) {
            return;
        }
        if (! $user->notification_telegram_enabled) {
            return;
        }

        $client = TelegramBotClient::fromConfig();
        (new TelegramNotificationService($client))->sendForNotification($user, $row);
    }
}
