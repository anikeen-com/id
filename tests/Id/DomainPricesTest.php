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
        $this->assertSame($attributes, $this->sentJson(0));
    }

    public function test_price_with_first_period(): void
    {
        $this->respond(200, ['data' => [
            'product_id' => 'p1', 'price' => 11.1, 'renewal_price' => 11.1, 'floored' => false,
            'first_period' => ['kind' => 'transfer', 'list_price' => 13.25, 'price' => 13.25, 'floored' => false],
        ]]);

        $quote = app(AnikeenId::class)->inventory()->price('p1', firstPeriod: FirstPeriod::TRANSFER);

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

        $request = $this->sent[0]['request'];
        $this->assertSame('POST /api/v1/client/products/quotes', $request->getMethod() . ' ' . $request->getUri()->getPath());
        $this->assertSame(['family' => 'domain', 'group' => 'team-1'], $this->sentJson(0));
        $this->assertCount(2, $quotes);
        $this->assertSame(PricingError::PRODUCT_UNAVAILABLE, $quotes[1]->error);
    }

    public function test_enums(): void
    {
        $this->assertSame(['setup', 'transfer', 'none'], FirstPeriod::all());
        $this->assertContains(PricingError::FIRST_PERIOD_WITH_TRIAL, PricingError::all());
        $this->assertFalse(PricingError::isCouponError(PricingError::FIRST_PERIOD_WITH_TRIAL));
    }
}
