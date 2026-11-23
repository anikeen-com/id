<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;

class ShippingTest extends TestCase
{
    private function line(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    public function test_no_request_until_a_method_is_called(): void
    {
        app(AnikeenId::class)->shipping();

        $this->assertSame([], $this->sent);
    }

    public function test_zones(): void
    {
        $this->respond(200, ['data' => [['id' => 'z1', 'name' => 'Germany', 'countries' => ['DE'], 'methods' => []]]]);
        $this->respond(201, ['data' => ['id' => 'z2', 'name' => 'EU']]);
        $this->respond(200, ['data' => ['id' => 'z2', 'name' => 'EU', 'sort' => 2]]);
        $this->respond(204);

        $shipping = app(AnikeenId::class)->shipping();

        $this->assertSame('Germany', $shipping->zones()[0]->name);
        $this->assertSame('z2', $shipping->createZone(['name' => 'EU', 'countries' => ['AT'], 'methods' => [['name' => 'DHL', 'gross_price' => '9.99']]])->id);
        $this->assertSame(2, $shipping->updateZone('z2', ['sort' => 2])->sort);
        $shipping->deleteZone('z2');

        $this->assertSame('GET /api/v1/client/shipping-zones Bearer app-token', $this->line(0));
        $this->assertSame('POST /api/v1/client/shipping-zones Bearer app-token', $this->line(1));
        $this->assertSame('9.99', $this->sentJson(1)['methods'][0]['gross_price']);
        $this->assertSame('PATCH /api/v1/client/shipping-zones/z2 Bearer app-token', $this->line(2));
        $this->assertSame('DELETE /api/v1/client/shipping-zones/z2 Bearer app-token', $this->line(3));
    }

    public function test_zone_returns_null_when_unknown(): void
    {
        $this->respond(200, ['data' => ['id' => 'z1', 'name' => 'Germany', 'countries' => ['DE'], 'methods' => [['id' => 'm1', 'name' => 'DHL Paket']]]]);
        $this->respond(404, ['message' => 'Shipping zone not found.', 'error' => 'shipping_zone_not_found']);

        $shipping = app(AnikeenId::class)->shipping();

        $this->assertSame('DHL Paket', $shipping->zone('z1')->methods[0]->name);
        $this->assertNull($shipping->zone('nope'));
        $this->assertSame('GET /api/v1/client/shipping-zones/z1 Bearer app-token', $this->line(0));
        $this->assertSame('GET /api/v1/client/shipping-zones/nope Bearer app-token', $this->line(1));
    }

    public function test_rates(): void
    {
        $this->respond(200, ['data' => ['zone' => ['id' => 'z1', 'name' => 'Germany'], 'methods' => [['id' => 'm1', 'name' => 'DHL Paket', 'price' => '3.99']]]]);

        $rates = app(AnikeenId::class)->shipping()->rates('DE', '53123', goodsTotal: '15.00');

        $this->assertSame('3.99', $rates->methods[0]->price);
        $this->assertSame('GET /api/v1/client/shipping-rates Bearer app-token', $this->line(0));
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame(['country_iso' => 'DE', 'postal_code' => '53123', 'goods_total' => '15.00'], $query);
    }
}
