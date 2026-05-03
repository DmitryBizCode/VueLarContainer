<?php

namespace App\Services\Notifications;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Throwable;

final class MailtrapQuotaExceptionDetector
{
    /**
     * Detect Mailtrap / HTTP responses that indicate quota, billing, or rate limits.
     * Walks the previous-exception chain (Mailtrap transport wraps HTTP client errors).
     */
    public static function isQuotaOrRateLimitRelated(Throwable $e): bool
    {
        $statusCodes = [];
        $messages = [];

        $current = $e;
        while ($current !== null) {
            $messages[] = $current->getMessage();
            if ($current instanceof ClientException || $current instanceof ServerException) {
                try {
                    $statusCodes[] = $current->getResponse()->getStatusCode();
                } catch (Throwable) {
                    // ignore
                }
            }
            $current = $current->getPrevious();
        }

        foreach ($statusCodes as $code) {
            if (in_array($code, [402, 429], true)) {
                return true;
            }
        }

        $blob = strtolower(implode(' ', $messages));
        foreach ([
            'rate limit',
            'too many requests',
            'quota',
            'billing',
            'payment required',
            'plan limit',
            'credit',
            'exceeded',
        ] as $needle) {
            if (str_contains($blob, $needle)) {
                return true;
            }
        }

        return false;
    }
}
