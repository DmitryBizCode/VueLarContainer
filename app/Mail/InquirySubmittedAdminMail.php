<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquirySubmittedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Inquiry $inquiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New inquiry #'.$this->inquiry->id.' — '.$this->inquiry->email,
        );
    }

    public function content(): Content
    {
        $brand = config('notifications.brand_name', config('app.name'));

        return new Content(
            view: 'emails.inquiries.submitted-admin',
            with: [
                'brandName' => $brand,
                'emailTitle' => 'New inquiry',
                'badge' => 'Operations',
                'inquiryId' => $this->inquiry->id,
                'name' => $this->inquiry->name,
                'email' => $this->inquiry->email,
                'subject' => $this->inquiry->subject ?? '—',
                'submittedAt' => $this->inquiry->created_at?->format('Y-m-d H:i T') ?? now()->format('Y-m-d H:i T'),
                'submitterUserId' => $this->inquiry->submitted_by_user_id,
                'body' => $this->inquiry->message,
            ],
        );
    }
}
