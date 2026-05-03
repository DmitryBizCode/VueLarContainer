@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 12px;font-size:18px;font-weight:bold;color:#18181b;">Your request has been received</h1>
    <p style="margin:0 0 16px;">Hello{{ $name ? ', '.$name : '' }},</p>
    <p style="margin:0 0 16px;">Thank you for contacting <strong>{{ $brandName }}</strong>. We have saved your message and our team will review it shortly.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:16px 0;background:#f4f4f5;border-radius:8px;">
        <tr>
            <td style="padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#3f3f46;">
                <p style="margin:0 0 8px;"><strong>Reference</strong> · #{{ $inquiryId }}</p>
                <p style="margin:0 0 8px;"><strong>Subject</strong> · {{ $subject }}</p>
                <p style="margin:0 0 8px;"><strong>Status</strong> · <span style="display:inline-block;padding:2px 8px;border-radius:9999px;background:#dbeafe;color:#1e40af;font-size:12px;font-weight:bold;">New</span></p>
                <p style="margin:8px 0 0;"><strong>Summary</strong></p>
                <p style="margin:4px 0 0;color:#52525b;">{{ $summary }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:16px 0 0;"><strong>What happens next</strong></p>
    <p style="margin:8px 0 0;">We typically respond within one business day. If your request is urgent, reply to this email or call the number on our website.</p>
@endsection

@section('cta')
    <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;">Open your dashboard</a>
@endsection

@section('footer')
    <p style="margin:0 0 8px;">If the button does not work, copy this link into your browser:</p>
    <p style="margin:0;word-break:break-all;"><a href="{{ $dashboardUrl }}" style="color:#2563eb;">{{ $dashboardUrl }}</a></p>
    <p style="margin:16px 0 0;">Questions? Contact us at <a href="mailto:{{ $supportEmail }}" style="color:#2563eb;">{{ $supportEmail }}</a></p>
@endsection
