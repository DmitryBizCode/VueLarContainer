<?php

return [

    'admin_inbox' => env('MAIL_CONTACT_ADMIN'),

    'new_message_email_enabled' => env('NOTIFY_NEW_MESSAGE_EMAIL', true),

    'support_email' => env('MAIL_SUPPORT_ADDRESS', env('MAIL_FROM_ADDRESS')),

    'brand_name' => env('MAIL_BRAND_NAME', config('app.name')),

    // When true, every created in-app notification is also emailed to the user.
    'in_app_email_enabled' => env('NOTIFY_IN_APP_EMAIL', true),

    // When true, push each created in-app notification to user's Telegram (if linked).
    'telegram_enabled' => env('NOTIFY_TELEGRAM', false),

    /*
    | When true, admin inquiry copy is emailed only if the contact form was submitted by a logged-in user.
    | Default false so guest inquiries still reach MAIL_CONTACT_ADMIN.
    */
    'inquiry_admin_mail_requires_authenticated_submitter' => env('MAIL_INQUIRY_ADMIN_REQUIRES_AUTH_SUBMITTER', false),

    /*
    | After Mailtrap returns quota / rate-limit style errors, pause outbound API sends for this many seconds
    | (cache key mailtrap_sending_paused_until). Only applies when default mailer uses transport mailtrap-sdk.
    */
    'mail_quota_cache_ttl' => (int) env('MAIL_MAILTRAP_QUOTA_CACHE_TTL', 3600),

];
