<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Enums\FirstPeriod;
use Anikeen\Id\Enums\PricingError;
use Anikeen\Id\Enums\ProductKind;

/**
 * Domains as services (envoyr/anikeen-tasks#237): purchase prices, first-period prices, quotes.
 */
class DomainPricesTest extends TestCase
{
    public function test_create_a_domain_with_purchase_prices(): void
    {
        $this->respond(201, ['data' => ['id' => 'p1', 'sku' => 'domain-de', 'cost_price' => 10.0]]);

        $attributes = [
            'sku' => 'domain-de', 'name' => 'Domain .de', 'kind' => ProductKind::SERVICE, 'unit' => 'year', 'family' => 'domain', 'price' => '11.10',
            'setup_price' => '5.73', 'transfer_price' => '13.25', 'cost_price' => '10.00', 'cost_setup_price' => '5.00', 'cost_transfer_price' => '12.00',
        ];
        $product = app(AnikeenId::class)->inventory()->createProduct($attributes);

        $this->assertEquals(10, $product->cost_price);
        $this->assertSame('POST /api/v1/client/products Bearer app-token', $this->line(0));
        $this->assertSame($attributes, $this->sentJson(0));
    }

    public function test_price_with_first_period(): void
    {
        $this->respond(200, ['data' => [
            'product_id' => 'p1', 'price' => 11.1, 'renewal_price' => 11.1, 'floored' => false,
            'first_period' => ['kind' => 'transfer', 'list_price' => 13.25, 'price' => 13.25, 'floored' => false],
        ]]);

        $quote = app(AnikeenId::class)->inventory()->price('p1', firstPeriod: FirstPeriod::TRANSFER);

        $this->assertSame('GET /api/v1/client/products/p1/price Bearer app-token', $this->line(0));
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame(['first_period' => 'transfer'], $query);
        $this->assertSame(13.25, $quote->first_period->price);
        $this->assertSame(11.1, $quote->renewal_price);
    }

    public function test_quotes_of_a_family(): void
    {
        $this->respond(200, ['data' => [
            ['product_id' => 'p1', 'price' => 11.1, 'first_period' => null],
            ['product_id' => 'p2', 'error' => 'product_unavailable', 'message' => 'The product is not available.'],
        ]]);

        $quotes = app(AnikeenId::class)->inventory()->quotes(['family' => 'domain', 'group' => 'team-1', 'coupon_code' => null]);

        $this->assertSame('POST /api/v1/client/products/quotes Bearer app-token', $this->line(0));
        $this->assertSame(['family' => 'domain', 'group' => 'team-1'], $this->sentJson(0));
        $this->assertCount(2, $quotes);
        $this->assertSame(PricingError::PRODUCT_UNAVAILABLE, $quotes[1]->error);
    }

    public function test_quotes_by_id_are_asked_in_parts(): void
    {
        $ids = array_map(fn (int $i) => 'p' . $i, range(1, 1001));
        $this->respond(200, ['data' => array_map(fn (string $id) => ['product_id' => $id], array_slice($ids, 0, 1000))]);
        $this->respond(200, ['data' => [['product_id' => 'p1001']]]);

        $quotes = app(AnikeenId::class)->inventory()->quotes(['product_ids' => array_combine($ids, $ids), 'group' => 'team-1']);

        $this->assertCount(1001, $quotes);
        $this->assertSame('p1001', $quotes[1000]->product_id);
        $this->assertSame(['p1001'], $this->sentJson(1)['product_ids']);
        $this->assertSame(range(0, 999), array_keys($this->sentJson(0)['product_ids']));
        $this->assertSame('team-1', $this->sentJson(1)['group']);
    }

    private function line(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    public function test_enums(): void
    {
        $this->assertSame(['setup', 'transfer', 'none'], FirstPeriod::all());
        $this->assertContains(PricingError::FIRST_PERIOD_WITH_TRIAL, PricingError::all());
        $this->assertFalse(PricingError::isCouponError(PricingError::FIRST_PERIOD_WITH_TRIAL));
    }
}
