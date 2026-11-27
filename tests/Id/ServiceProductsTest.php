<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Enums\PricingError;
use Anikeen\Id\Enums\ProductKind;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\ValidationFailedException;
use Anikeen\Id\Resources\AppSubscription;
use Illuminate\Database\Eloquent\Model;

class ServiceProductsTest extends TestCase
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

    private function line(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    private function requestQuery(int $index): array
    {
        parse_str($this->sent[$index]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    private function page(array $rows, int $page = 1, int $last = 1): array
    {
        return ['data' => $rows, 'meta' => ['current_page' => $page, 'last_page' => $last, 'total' => count($rows)]];
    }

    private function pricingRefusal(string $code, string $field = 'coupon_code'): array
    {
        return ['message' => 'Refused.', 'error' => $code, 'errors' => [$field => ['Refused.']]];
    }

    public function test_products_filter_by_kind_and_family(): void
    {
        $this->respond(200, $this->page([['id' => 'p1', 'sku' => 'server-s', 'kind' => 'service', 'family' => 'server', 'unit' => 'hour', 'monthly_cap' => 9.5, 'options' => ['cores' => 2]]]));

        $products = app(AnikeenId::class)->inventory()->products(['kind' => ProductKind::SERVICE, 'family' => 'server']);

        $this->assertSame('GET /api/v1/client/products Bearer app-token', $this->line(0));
        $this->assertSame(['kind' => 'service', 'family' => 'server', 'per_page' => '100', 'page' => '1'], $this->requestQuery(0));
        $this->assertSame(2, $products[0]->options->cores);
        $this->assertSame(9.5, $products[0]->monthly_cap);
    }

    public function test_create_a_service(): void
    {
        $this->respond(201, ['data' => ['id' => 'p1', 'sku' => 'server-s', 'kind' => 'service']]);

        $attributes = [
            'sku' => 'server-s', 'name' => 'Cloud Server S', 'kind' => ProductKind::SERVICE, 'unit' => 'hour', 'price' => '0.0137',
            'monthly_cap' => '9.50', 'family' => 'server', 'options' => ['cores' => 2, 'memory' => 4096], 'coupon_required' => false,
        ];
        $product = app(AnikeenId::class)->inventory()->createProduct($attributes);

        $this->assertSame('service', $product->kind);
        $this->assertSame('POST /api/v1/client/products Bearer app-token', $this->line(0));
        $this->assertSame($attributes, $this->sentJson(0));
    }

    public function test_price_quotes_a_service_for_a_customer(): void
    {
        $this->respond(200, ['data' => [
            'product_id' => 'p1', 'unit' => 'hour', 'list_price' => 0.02, 'list_monthly_cap' => 10.0, 'price' => 0.016, 'monthly_cap' => 8.0,
            'offer' => null, 'reseller_discount' => ['id' => 'rd1', 'family' => 'server', 'percent' => 20.0], 'coupon' => null,
        ]]);

        $quote = app(AnikeenId::class)->inventory()->price('p1', '01JUSER', 'team-1');

        $this->assertSame('GET /api/v1/client/products/p1/price Bearer app-token', $this->line(0));
        $this->assertSame(['user_id' => '01JUSER', 'group' => 'team-1'], $this->requestQuery(0));
        $this->assertSame(0.016, $quote->price);
        $this->assertEquals(20, $quote->reseller_discount->percent);
    }

    public function test_price_without_customer_sends_no_query(): void
    {
        $this->respond(200, ['data' => ['product_id' => 'p1', 'price' => 0.02]]);

        app(AnikeenId::class)->inventory()->price('p1');

        $this->assertSame([], $this->requestQuery(0));
    }

    public function test_a_refused_coupon_is_a_pricing_error(): void
    {
        $this->respond(422, $this->pricingRefusal(PricingError::COUPON_EXPIRED));

        try {
            app(AnikeenId::class)->inventory()->price('p1', couponCode: 'SUMMER');
            $this->fail('No exception thrown');
        } catch (ValidationFailedException $e) {
            $this->assertSame(['coupon_code' => 'SUMMER'], $this->requestQuery(0));
            $this->assertTrue($e->isPricingError());
            $this->assertTrue($e->isPricingError(PricingError::COUPON_EXPIRED));
            $this->assertFalse($e->isPricingError(PricingError::COUPON_USED_UP));
            $this->assertTrue(PricingError::isCouponError($e->error));
            $this->assertSame(['coupon_code' => ['Refused.']], $e->errors());
        }
    }

    public function test_other_errors_are_no_pricing_errors(): void
    {
        $this->assertFalse((new ApiException('Not found', 404, 'product_not_found'))->isPricingError());
        $this->assertFalse((new ApiException('Invalid', 422, null))->isPricingError());
        $this->assertFalse((new ApiException('Invalid', 422, 'price_changed'))->isPricingError());
        $this->assertTrue((new ApiException('Invalid', 422, 'product_not_a_service'))->isPricingError());
        $this->assertFalse(PricingError::isCouponError(PricingError::PRODUCT_UNAVAILABLE));
    }

    public function test_reseller_discounts_crud(): void
    {
        $discount = ['id' => 'rd1', 'group' => 'team-1', 'family' => 'server', 'percent' => 15.0, 'valid_from' => '2026-12-01T00:00:00+00:00'];
        $this->respond(200, $this->page([$discount], 1, 2));
        $this->respond(200, $this->page([['id' => 'rd2', 'group' => 'team-1', 'family' => null, 'percent' => 10.0]], 2, 2));
        $this->respond(201, ['data' => $discount]);
        $this->respond(200, ['data' => [...$discount, 'percent' => 20.0]]);
        $this->respond(404, ['message' => 'Reseller discount not found.', 'error' => 'reseller_discount_not_found']);
        $this->respond(204);

        $inventory = app(AnikeenId::class)->inventory();

        $this->assertSame(['rd1', 'rd2'], array_map(fn ($d) => $d->id, $inventory->resellerDiscounts(['group' => 'team-1'])));
        $this->assertSame(['group' => 'team-1', 'per_page' => '100', 'page' => '2'], $this->requestQuery(1));

        $this->assertSame('rd1', $inventory->createResellerDiscount(['group' => 'team-1', 'family' => 'server', 'percent' => 15])->id);
        $this->assertSame('POST /api/v1/client/reseller-discounts Bearer app-token', $this->line(2));
        $this->assertSame(['group' => 'team-1', 'family' => 'server', 'percent' => 15], $this->sentJson(2));

        $this->assertEquals(20, $inventory->updateResellerDiscount('rd1', ['percent' => 20])->percent);
        $this->assertSame('PATCH /api/v1/client/reseller-discounts/rd1 Bearer app-token', $this->line(3));

        $this->assertNull($inventory->resellerDiscount('nope'));
        $this->assertSame('GET /api/v1/client/reseller-discounts/nope Bearer app-token', $this->line(4));

        $inventory->deleteResellerDiscount('rd1');
        $this->assertSame('DELETE /api/v1/client/reseller-discounts/rd1 Bearer app-token', $this->line(5));
    }

    public function test_subscription_from_the_catalog(): void
    {
        $this->respond(201, ['data' => [
            'id' => '01JSUB', 'user_id' => '01JUSER', 'name' => 'Cloud Server S', 'unit' => 'hour', 'price' => 0.0123,
            'product_id' => 'p1', 'coupon_id' => 'c1',
            'pricing' => ['list_price' => '0.0137', 'price' => '0.0123', 'coupon' => ['id' => 'c1', 'code' => 'WELCOME', 'type' => 'percent', 'amount' => '10']],
        ]]);

        $subscription = $this->owner()->appSubscriptions()->create([
            'group' => 'team-1', 'product_id' => 'p1', 'coupon_code' => 'WELCOME', 'description' => 'web-01', 'idempotency_key' => 'server:1',
        ]);

        $this->assertSame('POST /api/v1/client/subscriptions Bearer app-token', $this->line(0));
        $this->assertSame(['group' => 'team-1', 'product_id' => 'p1', 'coupon_code' => 'WELCOME', 'description' => 'web-01', 'idempotency_key' => 'server:1', 'user_id' => '01JUSER'], $this->sentJson(0));
        $this->assertSame('p1', $subscription->product_id);
        $this->assertSame('c1', $subscription->coupon_id);
        $this->assertSame('WELCOME', $subscription->pricing->coupon->code);
    }

    public function test_checkout_session_items_from_the_catalog(): void
    {
        $this->respond(201, ['data' => ['id' => '01JCS', 'user_id' => '01JUSER', 'status' => 'open', 'subscriptions' => [['id' => '01JS1', 'product_id' => 'p1']]]]);

        $this->owner()->appCheckoutSessions()->create([
            'subscriptions' => [['group' => 'team-1', 'product_id' => 'p1', 'coupon_code' => 'WELCOME', 'name' => 'web-01']],
        ]);

        $this->assertSame('POST /api/v1/client/checkout-sessions Bearer app-token', $this->line(0));
        $this->assertSame([['group' => 'team-1', 'product_id' => 'p1', 'coupon_code' => 'WELCOME', 'name' => 'web-01']], $this->sentJson(0)['subscriptions']);
    }

    public function test_change_to_another_product(): void
    {
        $this->respond(200, ['data' => ['id' => '01JSUB', 'user_id' => '01JUSER', 'product_id' => 'p2']]);

        $subscription = (new AppSubscription((object) ['id' => '01JSUB']))->change(['apply' => AppSubscription::APPLY_NOW, 'product_id' => 'p2']);

        $this->assertSame('PUT /api/v1/client/subscriptions/01JSUB/change Bearer app-token', $this->line(0));
        $this->assertSame(['apply' => 'now', 'product_id' => 'p2'], $this->sentJson(0));
        $this->assertSame('p2', $subscription->product_id);
    }

    public function test_usage_records(): void
    {
        $record = ['id' => 'u1', 'subscription_id' => '01JSUB', 'meter' => 'server_traffic', 'quantity' => '12.5', 'unit' => 'gigabyte', 'unit_price' => '0.01', 'product_id' => 'p9'];
        $this->respond(201, ['data' => $record]);
        $this->respond(200, $this->page([$record], 1, 2));
        $this->respond(200, $this->page([['id' => 'u0']], 2, 2));
        $this->respond(200, ['data' => [...$record, 'voided_at' => '2026-12-02T00:00:00+00:00']]);

        $subscription = new AppSubscription((object) ['id' => '01JSUB']);

        $created = $subscription->recordUsage([
            'meter' => 'server_traffic', 'period_start' => '2026-11-01T00:00:00+01:00', 'period_end' => '2026-12-01T00:00:00+01:00',
            'quantity' => '12.5', 'product_id' => 'p9', 'idempotency_key' => 'traffic:srv1:2026-11',
        ]);
        $this->assertSame('POST /api/v1/client/subscriptions/01JSUB/usage Bearer app-token', $this->line(0));
        $this->assertSame('p9', $this->sentJson(0)['product_id']);
        $this->assertSame('u1', $created->id);

        $this->assertSame(['u1', 'u0'], array_map(fn ($r) => $r->id, $subscription->usageRecords('server_traffic')));
        $this->assertSame('GET /api/v1/client/subscriptions/01JSUB/usage Bearer app-token', $this->line(1));
        $this->assertSame(['meter' => 'server_traffic', 'per_page' => '100', 'page' => '2'], $this->requestQuery(2));

        $this->assertNotNull($subscription->voidUsage('u1')->voided_at);
        $this->assertSame('DELETE /api/v1/client/subscriptions/01JSUB/usage/u1 Bearer app-token', $this->line(3));
    }

    public function test_a_usage_record_with_goods_is_refused(): void
    {
        $this->respond(422, $this->pricingRefusal(PricingError::PRODUCT_NOT_METERED, 'product_id'));

        try {
            (new AppSubscription((object) ['id' => '01JSUB']))->recordUsage(['meter' => 'x', 'period_start' => 'a', 'period_end' => 'b', 'quantity' => 1, 'product_id' => 'p1', 'idempotency_key' => 'k']);
            $this->fail('No exception thrown');
        } catch (ApiException $e) {
            $this->assertTrue($e->isPricingError(PricingError::PRODUCT_NOT_METERED));
        }
    }
}
