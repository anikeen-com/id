<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\ValidationFailedException;
use Illuminate\Database\Eloquent\Model;

class InventoryTest extends TestCase
{
    private function page(array $rows, int $page = 1, int $last = 1): array
    {
        return ['data' => $rows, 'meta' => ['current_page' => $page, 'last_page' => $last, 'total' => count($rows)], 'links' => ['next' => null]];
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

    public function test_no_request_until_a_method_is_called(): void
    {
        app(AnikeenId::class)->inventory();

        $this->assertSame([], $this->sent);
    }

    public function test_products_follow_pages_with_filters(): void
    {
        $this->respond(200, $this->page([['id' => 'p1', 'sku' => 'A']], 1, 2));
        $this->respond(200, $this->page([['id' => 'p2', 'sku' => 'B']], 2, 2));

        $products = app(AnikeenId::class)->inventory()->products(['low_stock' => true]);

        $this->assertSame(['p1', 'p2'], array_map(fn ($p) => $p->id, $products));
        $this->assertSame('GET /api/v1/client/products Bearer app-token', $this->line(0));
        $this->assertSame(['low_stock' => '1', 'per_page' => '100', 'page' => '2'], $this->requestQuery(1));
    }

    public function test_product_returns_null_when_unknown(): void
    {
        $this->respond(404, ['message' => 'Product not found.', 'error' => 'product_not_found']);
        $this->respond(200, ['data' => ['id' => 'p1', 'sku' => 'A']]);

        $inventory = app(AnikeenId::class)->inventory();

        $this->assertNull($inventory->product('nope'));
        $this->assertSame('A', $inventory->product('p1')->sku);
    }

    public function test_product_by_sku(): void
    {
        $this->respond(200, $this->page([['id' => 'p1', 'sku' => '9783551804808']]));

        $this->assertSame('p1', app(AnikeenId::class)->inventory()->productBySku('9783551804808')->id);
        $this->assertSame(['sku' => '9783551804808', 'per_page' => '100', 'page' => '1'], $this->requestQuery(0));
    }

    public function test_create_product(): void
    {
        $this->respond(201, ['data' => ['id' => 'p1', 'sku' => 'A', 'stock_on_hand' => 5]]);

        $product = app(AnikeenId::class)->inventory()->createProduct([
            'sku' => 'A', 'name' => 'Band 1', 'type' => 'physical-reduced', 'gross_price' => '7.50', 'stock_on_hand' => 5,
        ]);

        $this->assertSame(5, $product->stock_on_hand);
        $this->assertSame('POST /api/v1/client/products Bearer app-token', $this->line(0));
        $this->assertSame(['sku' => 'A', 'name' => 'Band 1', 'type' => 'physical-reduced', 'gross_price' => '7.50', 'stock_on_hand' => 5], $this->sentJson(0));
    }

    public function test_upsert_sends_batches_of_100(): void
    {
        $rows = array_map(fn ($i) => ['sku' => "S{$i}", 'name' => "N{$i}", 'type' => 'physical', 'price' => '1.00'], range(1, 150));
        $this->respond(200, ['data' => array_map(fn ($row) => ['sku' => $row['sku'], 'created' => true], array_slice($rows, 0, 100))]);
        $this->respond(200, ['data' => array_map(fn ($row) => ['sku' => $row['sku'], 'created' => true], array_slice($rows, 100))]);

        $saved = app(AnikeenId::class)->inventory()->upsertProducts($rows);

        $this->assertCount(150, $saved);
        $this->assertSame('POST /api/v1/client/products/batch Bearer app-token', $this->line(1));
        $this->assertCount(100, $this->sentJson(0)['products']);
        $this->assertCount(50, $this->sentJson(1)['products']);
        $this->assertSame('S101', $this->sentJson(1)['products'][0]['sku']);
    }

    public function test_update_and_delete_product(): void
    {
        $this->respond(200, ['data' => ['id' => 'p1', 'available' => false]]);
        $this->respond(204);

        $inventory = app(AnikeenId::class)->inventory();

        $this->assertFalse($inventory->updateProduct('p1', ['available' => false])->available);
        $inventory->deleteProduct('p1');

        $this->assertSame('PATCH /api/v1/client/products/p1 Bearer app-token', $this->line(0));
        $this->assertSame(['available' => false], $this->sentJson(0));
        $this->assertSame('DELETE /api/v1/client/products/p1 Bearer app-token', $this->line(1));
    }

    public function test_booking_stock(): void
    {
        $this->respond(201, ['data' => ['type' => 'receipt', 'quantity' => 10, 'stock_on_hand' => 12]]);
        $this->respond(201, ['data' => ['type' => 'damage', 'quantity' => -1, 'stock_on_hand' => 11]]);
        $this->respond(201, ['data' => ['type' => 'count', 'quantity' => -2, 'stock_on_hand' => 9]]);

        $inventory = app(AnikeenId::class)->inventory();

        $this->assertSame(12, $inventory->receive('p1', 10, 'EK-2026-0001')->stock_on_hand);
        $this->assertSame(-1, $inventory->bookStock('p1', 'damage', 1, note: 'Ecke geknickt')->quantity);
        $this->assertSame('count', $inventory->count('p1', 9)->type);

        $this->assertSame('POST /api/v1/client/products/p1/stock-movements Bearer app-token', $this->line(0));
        $this->assertSame(['type' => 'receipt', 'quantity' => 10, 'reference' => 'EK-2026-0001'], $this->sentJson(0));
        $this->assertSame(['type' => 'damage', 'quantity' => 1, 'note' => 'Ecke geknickt'], $this->sentJson(1));
        $this->assertSame('POST /api/v1/client/products/p1/inventory-count Bearer app-token', $this->line(2));
        $this->assertSame(['stock_on_hand' => 9], $this->sentJson(2));
    }

    public function test_stock_movements(): void
    {
        $this->respond(200, $this->page([['id' => 'm1', 'type' => 'sale']]));

        $movements = app(AnikeenId::class)->inventory()->stockMovements('p1', ['type' => 'sale']);

        $this->assertSame('sale', $movements[0]->type);
        $this->assertSame('GET /api/v1/client/products/p1/stock-movements Bearer app-token', $this->line(0));
        $this->assertSame(['type' => 'sale', 'per_page' => '100', 'page' => '1'], $this->requestQuery(0));
    }

    public function test_conflicts_carry_their_error_code(): void
    {
        $this->respond(409, ['message' => 'The app already has a product with this SKU.', 'error' => 'sku_taken']);

        try {
            app(AnikeenId::class)->inventory()->createProduct(['sku' => 'A', 'name' => 'B', 'type' => 'physical', 'price' => 1]);
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertTrue($e->isConflict());
            $this->assertSame('sku_taken', $e->error);
        }
    }

    public function test_an_order_of_a_product_out_of_stock(): void
    {
        $this->respond(200, $this->page([])); // orders() reads the user's orders first
        $this->respond(409, ['message' => 'Only 1 of Band 1 available, 2 requested.', 'error' => 'out_of_stock', 'product_id' => 'p1', 'sku' => 'A', 'requested' => 2, 'available' => 1]);

        $user = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $user->anikeen_id_access_token = 'user-token';

        try {
            $user->orders()->create(['billing_address_id' => 'a1', 'items' => [['product_id' => 'p1', 'units' => 2]]]);
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertTrue($e->isOutOfStock());
            $this->assertSame(1, $e->body()->available);
        }

        $this->assertSame('POST /api/v1/orders Bearer user-token', $this->line(1));
        $this->assertSame(['billing_address_id' => 'a1', 'items' => [['product_id' => 'p1', 'units' => 2]]], $this->sentJson(1));
    }

    public function test_an_order_of_a_price_bound_product_at_an_outdated_price(): void
    {
        $this->respond(200, $this->page([])); // orders() reads the user's orders first
        $this->respond(422, [
            'message' => 'The price of Band 1 has changed: it is price-bound, order it at its catalog price.',
            'error' => 'price_changed',
            'errors' => ['items.0.gross_price' => ['The price of Band 1 has changed.']],
            'codes' => ['items.0.gross_price' => 'price_changed'],
            'product_id' => 'p1', 'sku' => '9783551804808', 'price' => 7.009346, 'gross_price' => 7.5,
            'items' => [['index' => 0, 'product_id' => 'p1', 'sku' => '9783551804808', 'price' => 7.009346, 'gross_price' => 7.5]],
        ]);

        $user = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $user->anikeen_id_access_token = 'user-token';

        try {
            $user->orders()->create(['billing_address_id' => 'a1', 'items' => [['product_id' => 'p1', 'units' => 1, 'gross_price' => '6.99']]]);
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertTrue($e->isPriceChanged());
            $this->assertFalse($e->isOutOfStock());
            $this->assertInstanceOf(ValidationFailedException::class, $e);
            $this->assertSame('price_changed', $e->errorCodeFor('items.0.gross_price'));
            $this->assertSame(7.5, $e->body()->gross_price);
            $this->assertSame(7.5, $e->body()->items[0]->gross_price);
        }
    }

    public function test_operators(): void
    {
        $this->respond(201, ['data' => ['id' => 'op1', 'name' => 'Example Figures', 'in_eu' => false]]);
        $this->respond(200, $this->page([['id' => 'op1', 'name' => 'Example Figures']]));
        $this->respond(200, ['data' => ['id' => 'op1', 'email' => 'safety@example.test']]);
        $this->respond(404, ['message' => 'Economic operator not found.', 'error' => 'operator_not_found']);
        $this->respond(204);

        $inventory = app(AnikeenId::class)->inventory();

        $this->assertFalse($inventory->createOperator(['name' => 'Example Figures', 'address' => '1-2-3', 'postal_code' => '100', 'city' => 'Tokyo', 'country_iso' => 'JP'])->in_eu);
        $this->assertSame('op1', $inventory->operators('Example')[0]->id);
        $this->assertSame('safety@example.test', $inventory->updateOperator('op1', ['email' => 'safety@example.test'])->email);
        $this->assertNull($inventory->operator('nope'));
        $inventory->deleteOperator('op1');

        $this->assertSame('POST /api/v1/client/product-operators Bearer app-token', $this->line(0));
        $this->assertSame(['search' => 'Example', 'per_page' => '100', 'page' => '1'], $this->requestQuery(1));
        $this->assertSame('PATCH /api/v1/client/product-operators/op1 Bearer app-token', $this->line(2));
        $this->assertSame('DELETE /api/v1/client/product-operators/op1 Bearer app-token', $this->line(4));
    }

    public function test_products_with_incomplete_safety_information(): void
    {
        $this->respond(200, $this->page([['id' => 'p1', 'safety' => ['missing' => ['responsible_person']]]]));

        $products = app(AnikeenId::class)->inventory()->productsWithIncompleteSafety();

        $this->assertSame(['responsible_person'], $products[0]->safety->missing);
        $this->assertSame(['safety_incomplete' => '1', 'per_page' => '100', 'page' => '1'], $this->requestQuery(0));
    }

    public function test_purchasing_suppliers_and_purchase_orders(): void
    {
        $this->respond(201, ['data' => ['id' => 's1', 'name' => 'Carlsen']]);
        $this->respond(201, ['data' => ['id' => 'po1', 'status' => 'draft', 'items' => [['id' => 'i1']]]]);
        $this->respond(200, ['data' => ['id' => 'po1', 'status' => 'ordered']]);
        $this->respond(200, ['data' => ['id' => 'po1', 'status' => 'received']]);

        $inventory = app(AnikeenId::class)->inventory();
        $supplier = $inventory->createSupplier(['name' => 'Carlsen']);
        $order = $inventory->createPurchaseOrder(['supplier_id' => $supplier->id, 'items' => [['product_id' => 'p1', 'quantity' => 5]]]);
        $inventory->orderPurchaseOrder($order->id);
        $this->assertSame('received', $inventory->receivePurchaseOrder($order->id, ['i1' => 5], 'LS-1')->status);

        $this->assertSame('POST /api/v1/client/suppliers Bearer app-token', $this->line(0));
        $this->assertSame('POST /api/v1/client/purchase-orders/po1/receive Bearer app-token', $this->line(3));
        $this->assertSame(['items' => [['id' => 'i1', 'quantity' => 5]], 'delivery_note' => 'LS-1'], $this->sentJson(3));
    }

    public function test_warehouses_and_transfers(): void
    {
        $this->respond(201, ['data' => ['id' => 'w2', 'code' => 'ML', 'is_default' => false]]);
        $this->respond(201, ['data' => ['id' => 'm1', 'warehouse_id' => 'w2']]);
        $this->respond(201, ['data' => [['id' => 'm2'], ['id' => 'm3']]]);

        $inventory = app(AnikeenId::class)->inventory();
        $warehouse = $inventory->createWarehouse(['name' => 'Messelager', 'code' => 'ML']);
        $inventory->receive('p1', 4, 'LS-1', warehouseId: $warehouse->id);
        $this->assertCount(2, $inventory->transferStock('p1', 'w1', 'w2', 2));

        $this->assertSame('POST /api/v1/client/warehouses Bearer app-token', $this->line(0));
        $this->assertSame(['type' => 'receipt', 'quantity' => 4, 'reference' => 'LS-1', 'warehouse_id' => 'w2'], $this->sentJson(1));
        $this->assertSame('POST /api/v1/client/products/p1/stock-transfers Bearer app-token', $this->line(2));
        $this->assertSame(['from_warehouse_id' => 'w1', 'to_warehouse_id' => 'w2', 'quantity' => 2], $this->sentJson(2));
    }

    public function test_warehouse_and_supplier_return_null_when_unknown(): void
    {
        $this->respond(200, ['data' => ['id' => 'w1', 'code' => 'HL', 'is_default' => true]]);
        $this->respond(404, ['message' => 'Warehouse not found.', 'error' => 'warehouse_not_found']);
        $this->respond(200, ['data' => ['id' => 's1', 'name' => 'Carlsen', 'lead_time_days' => 3]]);
        $this->respond(404, ['message' => 'Supplier not found.', 'error' => 'supplier_not_found']);

        $inventory = app(AnikeenId::class)->inventory();

        $this->assertSame('HL', $inventory->warehouse('w1')->code);
        $this->assertNull($inventory->warehouse('nope'));
        $this->assertSame(3, $inventory->supplier('s1')->lead_time_days);
        $this->assertNull($inventory->supplier('nope'));

        $this->assertSame('GET /api/v1/client/warehouses/w1 Bearer app-token', $this->line(0));
        $this->assertSame('GET /api/v1/client/warehouses/nope Bearer app-token', $this->line(1));
        $this->assertSame('GET /api/v1/client/suppliers/s1 Bearer app-token', $this->line(2));
        $this->assertSame('GET /api/v1/client/suppliers/nope Bearer app-token', $this->line(3));
    }

    public function test_other_errors_are_thrown_when_finding_one(): void
    {
        $this->respond(403, ['message' => 'Read-only sales channel.', 'error' => 'channel_read_only']);

        try {
            app(AnikeenId::class)->inventory()->supplier('s1');
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertSame('channel_read_only', $e->error);
        }
    }

    public function test_price_history(): void
    {
        $this->respond(200, ['data' => ['product_id' => 'p1', 'gross_price' => 10.0, 'changes' => [['source' => 'jpc', 'previous_gross_price' => 9.5, 'gross_price' => 10.0]]]]);

        $history = app(AnikeenId::class)->inventory()->priceHistory('p1');

        $this->assertSame('jpc', $history->changes[0]->source);
        $this->assertSame('GET /api/v1/client/products/p1/prices Bearer app-token', $this->line(0));
    }
}
