<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Exceptions\SignatureVerificationException;
use Anikeen\Id\Webhooks\Webhook;

class WebhookTest extends TestCase
{
    private string $secret = 'whsec_test';

    private string $payload = '{"id":"01JEVT","type":"order.completed","created":1790000000,"data":{"object":{"id":"01JORD"}}}';

    private function header(?int $timestamp = null, ?string $secret = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=" . hash_hmac('sha256', "{$timestamp}.{$this->payload}", $secret ?? $this->secret);
    }

    public function test_a_signed_event_is_returned(): void
    {
        $event = Webhook::constructEvent($this->payload, $this->header(), $this->secret);

        $this->assertSame('order.completed', $event['type']);
        $this->assertSame('01JORD', $event['data']['object']['id']);
    }

    public function test_a_wrong_secret_is_rejected(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($this->payload, $this->header(secret: 'whsec_other'), $this->secret);
    }

    public function test_a_changed_body_is_rejected(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent(str_replace('01JORD', '01JXXX', $this->payload), $this->header(), $this->secret);
    }

    public function test_old_requests_are_rejected(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($this->payload, $this->header(time() - 600), $this->secret);
    }

    public function test_a_missing_header_is_rejected(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($this->payload, null, $this->secret);
    }
}
