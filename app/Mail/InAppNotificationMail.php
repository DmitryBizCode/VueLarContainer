<?php

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InAppNotificationMail extends Mailable
{
    public function __construct(
        public User $recipient,
        public Notification $notification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.in-app',
            with: [
                'recipient' => $this->recipient,
                'notification' => $this->notification,
                'brandName' => config('notifications.brand_name', config('app.name')),
                'supportEmail' => config('notifications.support_email'),
            ],
        );
    }
}
