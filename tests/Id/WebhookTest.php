<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Exceptions\SignatureVerificationException;
use Anikeen\Id\Webhooks\EventType;
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

    public function test_a_renewal_carries_the_booked_period(): void
    {
        $this->payload = json_encode(['id' => '01JEVT', 'type' => EventType::SUBSCRIPTION_RENEWED, 'created' => time(), 'data' => [
            'object' => ['id' => '01JSUB', 'status' => 'active'],
            'period' => ['object' => 'subscription_period', 'subscription_id' => '01JSUB', 'period_start' => '2026-11-01T00:00:00+00:00', 'period_end' => '2026-12-01T00:00:00+00:00', 'periods' => 1, 'first_period' => true, 'unit' => 'month', 'order_id' => '01JORD', 'order_item_id' => '01JITEM', 'items' => [], 'currency_iso' => 'EUR', 'net_amount' => 8],
        ]]);

        $event = Webhook::constructEvent($this->payload, $this->header(), $this->secret);

        $this->assertSame('subscription.renewed', $event['type']);
        $this->assertSame('01JORD', $event['data']['period']['order_id']);
        $this->assertTrue($event['data']['period']['first_period']);
        $this->assertContains(EventType::SUBSCRIPTION_RENEWED, EventType::all());
    }

    public function test_refunds_have_their_own_event_type(): void
    {
        $this->assertSame('order.refunded', EventType::ORDER_REFUNDED);
        $this->assertContains(EventType::ORDER_REFUNDED, EventType::all());
    }

    public function test_billing_limit_events_have_their_own_types(): void
    {
        $this->assertSame('billing_limit.reached', EventType::BILLING_LIMIT_REACHED);
        $this->assertSame('billing_limit.available', EventType::BILLING_LIMIT_AVAILABLE);
        $this->assertContains(EventType::BILLING_LIMIT_REACHED, EventType::all());
        $this->assertContains(EventType::BILLING_LIMIT_AVAILABLE, EventType::all());
    }

    public function test_user_and_preference_events_have_their_own_types(): void
    {
        $this->assertSame('preference.updated', EventType::PREFERENCE_UPDATED);
        $this->assertSame('user.updated', EventType::USER_UPDATED);
        $this->assertSame('user.deleted', EventType::USER_DELETED);
        $this->assertContains(EventType::PREFERENCE_UPDATED, EventType::all());
        $this->assertContains(EventType::USER_UPDATED, EventType::all());
        $this->assertContains(EventType::USER_DELETED, EventType::all());
    }

    public function test_a_change_carries_the_previous_attributes(): void
    {
        $this->payload = json_encode(['id' => '01JEVT', 'type' => EventType::SUBSCRIPTION_UPDATED, 'created' => time(), 'data' => [
            'object' => ['id' => '01JSUB', 'price' => 14.99],
            'previous_attributes' => ['price' => 9.99],
        ]]);

        $event = Webhook::constructEvent($this->payload, $this->header(), $this->secret);

        $this->assertSame(9.99, $event['data']['previous_attributes']['price']);
    }

    public function test_the_app_access_revoked_event_is_known(): void
    {
        $this->assertSame('app_access.revoked', EventType::APP_ACCESS_REVOKED);
        $this->assertContains(EventType::APP_ACCESS_REVOKED, EventType::all());
    }
}
