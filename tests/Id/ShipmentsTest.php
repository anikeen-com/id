<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ResourceException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;

class ShipmentsTest extends TestCase
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

    private function order(): \Anikeen\Id\Resources\Order
    {
        $this->respond(200, ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0], 'links' => ['next' => null]]);
        $this->respond(200, ['data' => ['id' => 'ord1', 'status' => 'completed']]);

        return $this->owner()->orders()->find('ord1');
    }

    private function sentCall(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    public function test_an_order_is_shipped_with_carrier_tracking_and_items(): void
    {
        $order = $this->order();
        $this->respond(200, ['data' => []]);
        $this->respond(201, ['data' => ['id' => 'shp1', 'number' => 'LS-2026-0001', 'order_id' => 'ord1', 'carrier' => 'DHL']]);

        $shipment = $order->shipments()->create([
            'carrier' => 'DHL',
            'tracking_number' => '0034',
            'items' => [['order_item_id' => 'item1', 'units' => 1]],
        ]);

        $this->assertSame('LS-2026-0001', $shipment->number);
        $this->assertSame('GET /api/v1/orders/ord1/shipments Bearer user-token', $this->sentCall(2));
        $this->assertSame('POST /api/v1/orders/ord1/shipments Bearer user-token', $this->sentCall(3));
        $this->assertSame(['carrier' => 'DHL', 'tracking_number' => '0034', 'items' => [['order_item_id' => 'item1', 'units' => 1]]], $this->sentJson(3));
    }

    public function test_shipments_are_listed_and_the_delivery_note_is_downloaded(): void
    {
        $order = $this->order();
        $this->respond(200, ['data' => [['id' => 'shp1', 'number' => 'LS-2026-0001']]]);
        $this->respond(200, ['data' => ['id' => 'shp1', 'number' => 'LS-2026-0001', 'order_id' => 'ord1']]);
        $this->http->append(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.7 test'));

        $shipments = $order->shipments();
        $this->assertSame('LS-2026-0001', $shipments->toArray()[0]->number);

        $pdf = $shipments->find('shp1')->deliveryNote();

        $this->assertSame('%PDF-1.7 test', $pdf);
        $this->assertSame('GET /api/v1/orders/ord1/shipments/shp1/delivery-note Bearer user-token', $this->sentCall(4));
    }

    public function test_refusals_keep_their_status(): void
    {
        $order = $this->order();
        $this->respond(200, ['data' => []]);
        $this->respond(409, ['message' => 'Only paid orders can be shipped.', 'error' => 'order_not_paid']);

        try {
            $order->shipments()->create();
            $this->fail('expected a ResourceException');
        } catch (ResourceException $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertStringContainsString('Only paid orders can be shipped', $e->getMessage());
        }
    }
}
