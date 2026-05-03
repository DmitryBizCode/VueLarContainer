@php($brand = $brandName ?? config('notifications.brand_name', config('app.name')))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $emailTitle ?? $brand }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background-color:#f4f4f5;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="border-collapse:collapse;max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;box-shadow:0 4px 6px rgba(0,0,0,0.05);">
                <tr>
                    <td style="padding:24px 28px;background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%);">
                        <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:bold;color:#fbbf24;">{{ $brand }}</p>
                        @isset($badge)
                            <p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.06em;color:rgba(255,255,255,0.9);">{{ $badge }}</p>
                        @endisset
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 28px 8px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.55;color:#27272a;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 28px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#52525b;">
                        @hasSection('cta')
                            <table role="presentation" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:20px 0 0;">
                                <tr>
                                    <td style="border-radius:8px;background-color:#2563eb;">
                                        @yield('cta')
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 28px 24px;background-color:#fafafa;border-top:1px solid #e4e4e7;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#71717a;">
                        @yield('footer')
                    </td>
                </tr>
            </table>
            <p style="margin:16px 0 0;max-width:600px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#a1a1aa;text-align:center;">
                {{ $brand }} · Maritime logistics
            </p>
        </td>
    </tr>
</table>
</body>
</html>
