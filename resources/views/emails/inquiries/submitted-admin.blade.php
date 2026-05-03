@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 12px;font-size:18px;font-weight:bold;color:#18181b;">New website inquiry</h1>
    <p style="margin:0 0 16px;">A visitor submitted the contact form on your site.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:16px 0;background:#f4f4f5;border-radius:8px;">
        <tr>
            <td style="padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#3f3f46;">
                <p style="margin:0 0 8px;"><strong>Inquiry ID</strong> · #{{ $inquiryId }}</p>
                <p style="margin:0 0 8px;"><strong>Name</strong> · {{ $name }}</p>
                <p style="margin:0 0 8px;"><strong>Email</strong> · <a href="mailto:{{ $email }}" style="color:#2563eb;">{{ $email }}</a></p>
                <p style="margin:0 0 8px;"><strong>Subject</strong> · {{ $subject }}</p>
                <p style="margin:0 0 8px;"><strong>Submitted</strong> · {{ $submittedAt }}</p>
                @if($submitterUserId)
                    <p style="margin:0 0 8px;"><strong>Logged-in user ID</strong> · {{ $submitterUserId }}</p>
                @endif
                <p style="margin:8px 0 0;"><strong>Message</strong></p>
                <p style="margin:4px 0 0;white-space:pre-wrap;color:#52525b;">{{ $body }}</p>
            </td>
        </tr>
    </table>
@endsection

@section('footer')
    <p style="margin:0;">This is an automated operations notification from {{ $brandName }}.</p>
@endsection
