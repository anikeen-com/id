<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Resources\AppCharge;
use Anikeen\Id\Resources\BillingLimit;
use DateTimeImmutable;

class AppChargesTest extends TestCase
{
    private function sentCall(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    private function charge(array $overrides = []): array
    {
        return ['data' => [
            'object' => 'charge', 'id' => '01JCHARGE', 'user_id' => '01JUSER', 'status' => 'open',
            'idempotency_key' => 'shipment-123', 'name' => 'Signed card', 'description' => null,
            'type' => 'physical', 'quantity' => 2, 'unit' => 'piece', 'unit_price' => '4.000000',
            'net_amount' => '8.00', 'gross_amount' => '9.52', 'currency_iso' => 'EUR', 'group' => null,
            'occurred_at' => '2026-11-06T10:00:00+00:00', 'reference' => 'order-77', 'metadata' => ['parcel' => 'p-1'],
            'order_id' => '01JORDER', 'voided_at' => null, 'void_reason' => null, 'created_at' => '2026-11-06T10:00:01+00:00',
            ...$overrides,
        ]];
    }

    private function limit(array $overrides = []): array
    {
        return ['data' => [
            'object' => 'billing_limit', 'user_id' => '01JUSER', 'currency' => 'EUR', 'basis' => 'gross',
            'limit' => '100.00', 'used' => '82.40', 'used_by_app' => '60.00', 'remaining' => '17.60',
            'remaining_net' => '14.78', 'vat_rate' => 19, 'reached' => false,
            'resets_at' => '2026-12-01T02:00:00Z', 'version' => 7,
            ...$overrides,
        ]];
    }

    public function test_a_charge_is_booked_with_the_app_token(): void
    {
        $this->respond(201, $this->charge());

        $attributes = [
            'idempotency_key' => 'shipment-123',
            'name' => 'Signed card',
            'type' => 'physical',
            'quantity' => 2,
            'unit_price' => '4.00',
            'reference' => 'order-77',
            'metadata' => ['parcel' => 'p-1'],
        ];
        $charge = app(AnikeenId::class)->createChargeForUser('01JUSER', $attributes);

        $this->assertSame('POST /api/v1/client/users/01JUSER/charges Bearer app-token', $this->sentCall(0));
        $this->assertSame($attributes, $this->sentJson(0));
        $this->assertInstanceOf(AppCharge::class, $charge);
        $this->assertSame('01JCHARGE', $charge->id());
        $this->assertSame('open', $charge->status());
        $this->assertTrue($charge->isOpen());
        $this->assertFalse($charge->isBilled());
        $this->assertFalse($charge->isVoided());
        $this->assertSame('8.00', $charge->netAmount());
        $this->assertSame('9.52', $charge->grossAmount());
        $this->assertSame('01JORDER', $charge->orderId());
        $this->assertSame(['parcel' => 'p-1'], $charge->metadata());
        $this->assertEquals(new DateTimeImmutable('2026-11-06T10:00:00+00:00'), $charge->occurredAt());
        $this->assertSame(2, $charge->quantity);
        $this->assertSame('order-77', $charge->reference);
        $this->assertNull($charge->voided_at);
        $this->assertSame('Signed card', $charge->jsonSerialize()['name']);
    }

    public function test_empty_metadata_is_an_empty_array(): void
    {
        $this->respond(200, $this->charge(['metadata' => new \stdClass()]));

        $this->assertSame([], app(AnikeenId::class)->findChargeForUser('01JUSER', '01JCHARGE')->metadata());
    }

    public function test_a_charge_is_found_with_the_app_token(): void
    {
        $this->respond(200, $this->charge(['status' => 'billed']));

        $charge = app(AnikeenId::class)->findChargeForUser('01JUSER', '01JCHARGE');

        $this->assertSame('GET /api/v1/client/users/01JUSER/charges/01JCHARGE Bearer app-token', $this->sentCall(0));
        $this->assertTrue($charge->isBilled());
    }

    public function test_a_charge_is_voided_with_and_without_a_reason(): void
    {
        $voided = ['status' => 'voided', 'order_id' => null, 'voided_at' => '2026-11-06T11:00:00+00:00', 'void_reason' => 'Parcel lost'];
        $this->respond(200, $this->charge($voided));
        $this->respond(200, $this->charge($voided));

        $charge = app(AnikeenId::class)->voidChargeForUser('01JUSER', '01JCHARGE', 'Parcel lost');
        app(AnikeenId::class)->voidChargeForUser('01JUSER', '01JCHARGE');

        $this->assertSame('POST /api/v1/client/users/01JUSER/charges/01JCHARGE/void Bearer app-token', $this->sentCall(0));
        $this->assertSame(['reason' => 'Parcel lost'], $this->sentJson(0));
        $this->assertSame('POST /api/v1/client/users/01JUSER/charges/01JCHARGE/void Bearer app-token', $this->sentCall(1));
        $this->assertSame([], $this->sentJson(1));
        $this->assertTrue($charge->isVoided());
        $this->assertNull($charge->orderId());
        $this->assertSame('Parcel lost', $charge->void_reason);
    }

    public function test_path_segments_are_encoded(): void
    {
        $this->respond(200, $this->charge());

        app(AnikeenId::class)->findChargeForUser('01J/USER', '01J CHARGE');

        $this->assertSame('/api/v1/client/users/01J%2FUSER/charges/01J%20CHARGE', $this->sent[0]['request']->getUri()->getPath());
    }

    public function test_a_charge_over_the_billing_limit_carries_the_details(): void
    {
        $this->respond(409, [
            'message' => 'The customer\'s billing limit is reached.', 'error' => 'billing_limit_reached',
            'limit' => '100.00', 'used' => '97.40', 'remaining' => '2.60', 'remaining_net' => '2.18',
            'required' => '4.76', 'currency' => 'EUR', 'resets_at' => '2026-12-01T02:00:00Z',
        ]);

        try {
            app(AnikeenId::class)->createChargeForUser('01JUSER', ['idempotency_key' => 'k', 'name' => 'Card', 'quantity' => 1, 'unit_price' => '4.00']);
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isBillingLimitReached());
            $this->assertTrue($e->isConflict());
            $this->assertSame(409, $e->status);
            $this->assertSame('2.60', $e->body()->remaining);
            $this->assertSame('4.76', $e->body()->required);
            $this->assertSame('2026-12-01T02:00:00Z', $e->body()->resets_at);
        }
    }

    public function test_other_conflicts_are_not_the_billing_limit(): void
    {
        $this->respond(409, ['message' => 'The charge is more than this app may charge at once.', 'error' => 'charge_exceeds_maximum', 'max_charge_net' => '50.00']);

        try {
            app(AnikeenId::class)->createChargeForUser('01JUSER', ['idempotency_key' => 'k', 'name' => 'Card', 'quantity' => 1, 'unit_price' => '60.00']);
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertFalse($e->isBillingLimitReached());
            $this->assertSame('charge_exceeds_maximum', $e->error);
            $this->assertSame('50.00', $e->body()->max_charge_net);
        }
    }

    public function test_an_already_billed_charge_cannot_be_voided(): void
    {
        $this->respond(409, ['message' => 'The charge is on a closed bill; only a refund of that order credits it.', 'error' => 'charge_already_billed', 'charge_id' => '01JCHARGE']);

        try {
            app(AnikeenId::class)->voidChargeForUser('01JUSER', '01JCHARGE');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertSame('charge_already_billed', $e->error);
            $this->assertSame('01JCHARGE', $e->body()->charge_id);
        }
    }

    public function test_an_unknown_charge_is_not_found(): void
    {
        $this->respond(404, ['message' => 'Charge not found.', 'error' => 'charge_not_found']);

        try {
            app(AnikeenId::class)->findChargeForUser('01JUSER', '01JOTHER');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isNotFound());
            $this->assertSame('charge_not_found', $e->error);
        }
    }

    public function test_the_billing_limit_is_read_with_the_app_token(): void
    {
        $this->respond(200, $this->limit());

        $limit = app(AnikeenId::class)->billingLimitForUser('01JUSER');

        $this->assertSame('GET /api/v1/client/users/01JUSER/billing-limit Bearer app-token', $this->sentCall(0));
        $this->assertInstanceOf(BillingLimit::class, $limit);
        $this->assertTrue($limit->hasLimit());
        $this->assertSame('100.00', $limit->limit());
        $this->assertSame('82.40', $limit->used());
        $this->assertSame('60.00', $limit->usedByApp());
        $this->assertSame('17.60', $limit->remaining());
        $this->assertSame('14.78', $limit->remainingNet());
        $this->assertSame(19.0, $limit->vatRate());
        $this->assertSame('EUR', $limit->currency());
        $this->assertFalse($limit->isReached());
        $this->assertEquals(new DateTimeImmutable('2026-12-01T02:00:00Z'), $limit->resetsAt());
        $this->assertSame(7, $limit->version());
        $this->assertSame('gross', $limit->basis);
    }

    public function test_without_a_limit_the_app_sees_only_its_own_share(): void
    {
        $this->respond(200, $this->limit(['limit' => null, 'used' => null, 'remaining' => null, 'remaining_net' => null, 'vat_rate' => null]));

        $limit = app(AnikeenId::class)->billingLimitForUser('01JUSER');

        $this->assertFalse($limit->hasLimit());
        $this->assertNull($limit->limit());
        $this->assertNull($limit->used());
        $this->assertNull($limit->remaining());
        $this->assertNull($limit->remainingNet());
        $this->assertNull($limit->vatRate());
        $this->assertSame('60.00', $limit->usedByApp());
        $this->assertTrue($limit->allows('100000.00'));
    }

    public function test_allows_compares_with_the_remaining_amount(): void
    {
        $limit = new BillingLimit((object) $this->limit()['data']);

        $this->assertTrue($limit->allows('17.60'));
        $this->assertTrue($limit->allows('17.6'));
        $this->assertTrue($limit->allows('4.76'));
        $this->assertTrue($limit->allows(17.6));
        $this->assertTrue($limit->allows(17));
        $this->assertFalse($limit->allows('17.61'));
        $this->assertFalse($limit->allows('17.600001'));
        $this->assertFalse($limit->allows('100.00'));
        $this->assertFalse($limit->allows(1000));
    }

    public function test_a_reached_or_zero_limit_allows_nothing(): void
    {
        $reached = new BillingLimit((object) $this->limit(['used' => '100.00', 'remaining' => '0.00', 'remaining_net' => '0.00', 'reached' => true])['data']);
        $zero = new BillingLimit((object) $this->limit(['limit' => '0.00', 'used' => '0.00', 'remaining' => '0.00', 'reached' => true])['data']);

        $this->assertTrue($reached->isReached());
        $this->assertFalse($reached->allows('0.01'));
        $this->assertTrue($zero->hasLimit());
        $this->assertFalse($zero->allows('0.01'));
    }

    public function test_the_user_s_own_limit_has_no_app_share(): void
    {
        $limit = new BillingLimit((object) ['object' => 'billing_limit', 'user_id' => '01JUSER', 'currency' => 'EUR', 'basis' => 'gross', 'limit' => null, 'used' => '12.00', 'remaining' => null, 'remaining_net' => null, 'vat_rate' => 19.0, 'reached' => false, 'resets_at' => '2026-12-01T02:00:00Z', 'version' => 0]);

        $this->assertSame('12.00', $limit->used());
        $this->assertNull($limit->usedByApp());
        $this->assertSame(0, $limit->version());
    }

    public function test_a_refused_limit_read_is_an_api_exception(): void
    {
        $this->respond(403, ['message' => 'This app may not read customers\' billing limits.', 'error' => 'billing_limit_not_enabled']);

        try {
            app(AnikeenId::class)->billingLimitForUser('01JUSER');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('billing_limit_not_enabled', $e->error);
            $this->assertFalse($e->isBillingLimitReached());
        }
    }
}
