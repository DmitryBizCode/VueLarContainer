@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 12px;font-size:18px;font-weight:bold;color:#18181b;">New message</h1>
    <p style="margin:0 0 16px;">Hello{{ $recipientName ? ', '.$recipientName : '' }},</p>
    <p style="margin:0 0 16px;">You have a new message@if($context) regarding {{ $context }}@endif.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:16px 0;background:#f4f4f5;border-radius:8px;">
        <tr>
            <td style="padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#3f3f46;">
                <p style="margin:0 0 8px;"><strong>From</strong> · {{ $senderLabel }}</p>
                @if($subjectLine)
                    <p style="margin:0 0 8px;"><strong>Subject</strong> · {{ $subjectLine }}</p>
                @endif
                <p style="margin:0 0 8px;"><strong>Received</strong> · {{ $receivedAt }}</p>
                <p style="margin:8px 0 0;"><strong>Preview</strong></p>
                <p style="margin:4px 0 0;white-space:pre-wrap;color:#52525b;">{{ $preview }}</p>
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    <a href="{{ $openUrl }}" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">View message</a>
@endsection

@section('footer')
    <p style="margin:0 0 8px;">If the button does not work, open:</p>
    <p style="margin:0;word-break:break-all;"><a href="{{ $openUrl }}" style="color:#2563eb;">{{ $openUrl }}</a></p>
    <p style="margin:16px 0 0;">Support: <a href="mailto:{{ $supportEmail }}" style="color:#2563eb;">{{ $supportEmail }}</a></p>
@endsection
