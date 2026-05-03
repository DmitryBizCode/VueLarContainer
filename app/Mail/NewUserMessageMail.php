<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public UserMessage $userMessage,
        public User $recipient,
        public string $senderLabel,
    ) {}

    public function envelope(): Envelope
    {
        $sub = $this->userMessage->subject ?: 'New message';

        return new Envelope(
            subject: $sub.' — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        $brand = config('notifications.brand_name', config('app.name'));
        $name = trim(implode(' ', array_filter([$this->recipient->first_name, $this->recipient->last_name])));
        $openUrl = route('messages.show', ['message' => $this->userMessage->id], true);
        $preview = \Illuminate\Support\Str::limit(strip_tags($this->userMessage->body), 280);

        return new Content(
            view: 'emails.messages.new-message',
            with: [
                'brandName' => $brand,
                'emailTitle' => 'New message',
                'badge' => 'Inbox',
                'recipientName' => $name !== '' ? $name : null,
                'context' => null,
                'senderLabel' => $this->senderLabel,
                'subjectLine' => $this->userMessage->subject,
                'receivedAt' => $this->userMessage->created_at?->format('Y-m-d H:i T') ?? now()->format('Y-m-d H:i T'),
                'preview' => $preview,
                'openUrl' => $openUrl,
                'supportEmail' => config('notifications.support_email', config('mail.from.address')),
            ],
        );
    }
}
