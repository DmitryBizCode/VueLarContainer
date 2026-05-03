<?php

namespace Tests\Unit;

use App\Services\Notifications\MailtrapQuotaExceptionDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\RuntimeException as MailerRuntimeException;

class MailtrapQuotaExceptionDetectorTest extends TestCase
{
    public function test_detects_wrapped_http_429(): void
    {
        $response = new MockResponse('', ['http_code' => 429]);
        $httpEx = new ClientException($response);
        $wrapped = new MailerRuntimeException('transport failed', 0, $httpEx);

        $this->assertTrue(MailtrapQuotaExceptionDetector::isQuotaOrRateLimitRelated($wrapped));
    }

    public function test_detects_quota_keyword_in_message_chain(): void
    {
        $e = new \RuntimeException('API error: monthly quota exceeded');

        $this->assertTrue(MailtrapQuotaExceptionDetector::isQuotaOrRateLimitRelated($e));
    }

    public function test_generic_error_not_quota(): void
    {
        $e = new \RuntimeException('Connection timed out');

        $this->assertFalse(MailtrapQuotaExceptionDetector::isQuotaOrRateLimitRelated($e));
    }
}
