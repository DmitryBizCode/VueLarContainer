<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquirySubmittedUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
        public ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->inquiry->subject
            ? 'We received your message — '.$this->inquiry->subject
            : 'We received your message';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $brand = config('notifications.brand_name', config('app.name'));
        $summary = \Illuminate\Support\Str::limit(strip_tags($this->inquiry->message), 240);

        return new Content(
            view: 'emails.inquiries.submitted-user',
            with: [
                'brandName' => $brand,
                'emailTitle' => 'Request received',
                'badge' => 'Confirmation',
                'name' => $this->recipientName,
                'inquiryId' => $this->inquiry->id,
                'subject' => $this->inquiry->subject ?? 'General inquiry',
                'summary' => $summary,
                'dashboardUrl' => url('/dashboard'),
                'supportEmail' => config('notifications.support_email', config('mail.from.address')),
            ],
        );
    }
}
