<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\ConfirmationRequiredException;
use Illuminate\Database\Eloquent\Model;

class CheckoutConfirmationTest extends TestCase
{
    private function owner(): Model
    {
        $owner = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $owner->anikeen_id = '01JUSER';
        $owner->anikeen_id_access_token = 'user-token';

        return $owner;
    }

    public function test_a_draft_order_links_to_its_confirmation_with_the_return_url(): void
    {
        $this->respond(200, ['data' => []]); // orders() loads the list first
        $this->respond(201, ['data' => ['id' => '01JORD', 'status' => 'draft', 'confirmation_url' => 'https://id.anikeen.com/checkout/orders/01JORD']]);

        $order = $this->owner()->orders()->create(['billing_address_id' => '01JADDR', 'items' => []]);

        $this->assertSame('https://id.anikeen.com/checkout/orders/01JORD', $order->confirmationUrl());
        $this->assertSame(
            'https://id.anikeen.com/checkout/orders/01JORD?return_url=https%3A%2F%2Fshop.test%2Fdone%3Fcart%3D1',
            $order->confirmationUrl('https://shop.test/done?cart=1'),
        );
        $this->assertSame('POST /api/v1/orders', $this->sent[1]['request']->getMethod() . ' ' . $this->sent[1]['request']->getUri()->getPath());
    }

    public function test_checked_out_bookings_have_no_confirmation_url(): void
    {
        $this->respond(200, ['data' => []]);
        $this->respond(201, ['data' => ['id' => '01JORD', 'status' => 'completed', 'confirmation_url' => null]]);

        $this->assertNull($this->owner()->orders()->create([])->confirmationUrl('https://shop.test/done'));
    }

    public function test_checking_out_a_draft_as_a_third_party_app_points_to_the_confirmation(): void
    {
        $this->respond(200, ['data' => []]); // subscriptions() loads the list first
        $this->respond(201, ['data' => ['id' => '01JSUB', 'active' => false, 'confirmation_url' => 'https://id.anikeen.com/checkout/subscriptions/01JSUB']]);
        $this->respond(403, ['message' => 'The user has to confirm this on Anikeen ID.', 'error' => 'confirmation_required', 'confirmation_url' => 'https://id.anikeen.com/checkout/subscriptions/01JSUB']);

        $subscription = $this->owner()->subscriptions()->create(['name' => 'Pro', 'unit' => 'month', 'price' => 4.99]);

        try {
            $subscription->checkout();
            $this->fail('Expected a ConfirmationRequiredException.');
        } catch (ConfirmationRequiredException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertSame('https://id.anikeen.com/checkout/subscriptions/01JSUB', $e->confirmationUrl);
        }
    }

    public function test_a_trusted_app_lets_the_user_confirm_its_order(): void
    {
        $this->respond(201, ['data' => ['id' => '01JORD', 'status' => 'draft', 'group' => 'team-1', 'metadata' => ['topup' => '7'], 'expires_at' => '2026-11-05T10:00:00+00:00', 'confirmation_url' => 'https://id.anikeen.com/checkout/orders/01JORD']]);

        $result = app(AnikeenId::class)->createOrderForUser('01JUSER', [
            'group' => 'team-1',
            'confirm' => true,
            'return_url' => 'https://console.test/billing/topup',
            'metadata' => ['topup' => '7'],
            'items' => [['type' => 'digital', 'name' => 'Credit', 'unit' => 'onetime', 'units' => 1, 'gross_price' => '10.00']],
        ]);

        $this->assertSame('POST /api/v1/admin/orders Bearer app-token', $this->sent[0]['request']->getMethod() . ' ' . $this->sent[0]['request']->getUri()->getPath() . ' ' . $this->sent[0]['request']->getHeaderLine('Authorization'));
        $this->assertTrue($this->sentJson(0)['confirm']);
        $this->assertSame('https://console.test/billing/topup', $this->sentJson(0)['return_url']);
        $this->assertSame(['topup' => '7'], $this->sentJson(0)['metadata']);
        $this->assertSame('01JUSER', $this->sentJson(0)['user_id']);
        $this->assertSame('https://id.anikeen.com/checkout/orders/01JORD', $result->data->confirmation_url);
        $this->assertSame('7', $result->data->metadata->topup);
    }

    public function test_an_expired_confirmation_is_a_conflict(): void
    {
        $this->respond(200, ['data' => []]);
        $this->respond(201, ['data' => ['id' => '01JORD', 'status' => 'draft', 'confirmation_url' => 'https://id.anikeen.com/checkout/orders/01JORD']]);
        $this->respond(409, ['message' => 'The order was not confirmed in time.', 'error' => 'confirmation_expired']);

        $order = $this->owner()->orders()->create(['confirm' => true, 'return_url' => 'https://shop.test/done', 'metadata' => ['cart' => 1], 'items' => []]);

        $this->assertSame(['confirm' => true, 'return_url' => 'https://shop.test/done', 'metadata' => ['cart' => 1], 'items' => []], $this->sentJson(1));

        try {
            $order->checkout();
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isConflict());
            $this->assertSame('confirmation_expired', $e->error);
        }
    }
}
