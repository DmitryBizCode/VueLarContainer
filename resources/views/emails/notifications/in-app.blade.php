@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 10px 0; font-size:20px; line-height:1.3; color:#0f172a;">
        {{ $notification->title }}
    </h1>

    <p style="margin:0 0 14px 0; color:#334155; font-size:14px; line-height:1.6;">
        {{ $notification->message }}
    </p>

    @if(!empty($notification->action_url))
        <p style="margin:0 0 16px 0;">
            <a href="{{ $notification->action_url }}"
               style="display:inline-block; background:#1d4ed8; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; padding:10px 14px; border-radius:10px;">
                Open in {{ $brandName }}
            </a>
        </p>
    @endif

    <p style="margin:20px 0 0 0; color:#64748b; font-size:12px; line-height:1.6;">
        If you did not expect this message, contact us at
        <a href="mailto:{{ $supportEmail }}" style="color:#1d4ed8; text-decoration:none;">{{ $supportEmail }}</a>.
    </p>
@endsection

