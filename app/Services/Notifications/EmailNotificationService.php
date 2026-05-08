<?php

namespace App\Services\Notifications;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailNotificationService
{
    public const CACHE_KEY_MAILTRAP_PAUSED_UNTIL = 'mailtrap_sending_paused_until';

    public function send(Mailable $mailable, string|array $to): bool
    {
        if ($to === '' || $to === []) {
            return false;
        }

        if ($this->quotaGuardApplies() && $this->isSendingPausedByQuota()) {
            Log::warning('mail.skipped_quota_guard', [
                'mailable' => $mailable::class,
            ]);

            return false;
        }

        try {
            // send() — no extra Mailable queue; with QUEUE_CONNECTION=sync no queue worker is required.
            Mail::to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            if ($this->quotaGuardApplies() && MailtrapQuotaExceptionDetector::isQuotaOrRateLimitRelated($e)) {
                $this->suspendSendingForQuota();
                Log::critical('mail.mailtrap_quota_or_rate_limit', [
                    'mailable' => $mailable::class,
                    'message' => $e->getMessage(),
                ]);

                return false;
            }

            Log::error('mail.send_failed', [
                'mailable' => $mailable::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isSendingPausedByQuota(): bool
    {
        $until = Cache::get(self::CACHE_KEY_MAILTRAP_PAUSED_UNTIL);

        return is_numeric($until) && time() < (int) $until;
    }

    private function suspendSendingForQuota(): void
    {
        $ttl = max(60, (int) config('notifications.mail_quota_cache_ttl', 3600));
        $until = now()->addSeconds($ttl)->timestamp;
        Cache::put(self::CACHE_KEY_MAILTRAP_PAUSED_UNTIL, $until, $ttl);
    }

    private function quotaGuardApplies(): bool
    {
        $name = (string) config('mail.default', 'log');
        $transport = (string) config("mail.mailers.{$name}.transport", '');

        return $transport === 'mailtrap-sdk';
    }
}
