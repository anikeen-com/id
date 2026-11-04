<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;

class AppOrdersTest extends TestCase
{
    private function owner(): Model
    {
        $owner = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $owner->anikeen_id = '01JUSER';

        return $owner;
    }

    private function page(array $orders, int $page = 1, int $last = 1): array
    {
        return ['data' => $orders, 'meta' => ['current_page' => $page, 'last_page' => $last, 'total' => count($orders)], 'links' => ['next' => null]];
    }

    private function requestQuery(int $index): array
    {
        parse_str($this->sent[$index]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    public function test_pending_orders_of_a_user(): void
    {
        $this->respond(200, $this->page([['id' => 'o1', 'user_id' => '01JUSER', 'status' => 'pending', 'gross_total' => 11.9, 'next_payment_attempt_at' => null, 'payment_attempts' => 4]]));

        $orders = $this->owner()->appOrders()->pending();

        $this->assertSame('o1', $orders[0]->id);
        $this->assertSame(4, $orders[0]->payment_attempts);
        $request = $this->sent[0]['request'];
        $this->assertSame('GET /api/v1/client/orders Bearer app-token', $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization'));
        $this->assertSame(['status' => 'pending', 'user_id' => '01JUSER', 'per_page' => '100', 'page' => '1'], $this->requestQuery(0));
    }

    public function test_pending_orders_of_all_users_follow_pages(): void
    {
        $this->respond(200, $this->page([['id' => 'o1', 'user_id' => 'a']], 1, 2));
        $this->respond(200, $this->page([['id' => 'o2', 'user_id' => 'b']], 2, 2));

        $orders = app(AnikeenId::class)->appOrders()->pending(['group' => 'team-1']);

        $this->assertSame(['o1', 'o2'], array_map(fn ($o) => $o->id, $orders));
        $this->assertSame(['group' => 'team-1', 'status' => 'pending', 'per_page' => '100', 'page' => '2'], $this->requestQuery(1));
    }

    public function test_find_returns_null_for_unknown_and_other_users_orders(): void
    {
        $this->respond(404, ['message' => 'Order not found.', 'error' => 'order_not_found']);
        $this->respond(200, ['data' => ['id' => 'o1', 'user_id' => 'someone-else']]);
        $this->respond(200, ['data' => ['id' => 'o1', 'user_id' => '01JUSER']]);

        $orders = $this->owner()->appOrders();

        $this->assertNull($orders->find('unknown'));
        $this->assertNull($orders->find('o1'));
        $this->assertSame('o1', $orders->find('o1')->id);
    }

    public function test_orders_list_their_lines_and_invoice(): void
    {
        $this->respond(200, ['data' => [
            'id' => 'o1',
            'user_id' => '01JUSER',
            'group' => 'team-1',
            'metadata' => ['topup' => '7'],
            'items' => [
                ['id' => 'i1', 'subscription_id' => '01JSUB', 'units' => 1, 'price' => 10, 'period_start' => '2026-11-01T00:00:00+00:00', 'period_end' => '2026-12-01T00:00:00+00:00', 'net_amount' => 10, 'discount' => null],
                ['id' => 'i2', 'subscription_id' => '01JSUB', 'units' => 1, 'price' => -2, 'period_start' => '2026-11-01T00:00:00+00:00', 'period_end' => '2026-12-01T00:00:00+00:00', 'net_amount' => -2, 'discount' => ['label' => 'SUMMER20', 'percent_off' => 20, 'amount_off' => null]],
            ],
            'invoice' => ['id' => 'inv1', 'number' => 'RE-2026-0001', 'status' => 'completed', 'pdf_url' => 'https://id.test/api/v1/client/orders/o1/invoice'],
        ]]);

        $order = $this->owner()->appOrders()->find('o1');

        $this->assertSame('7', $order->metadata->topup);
        $this->assertSame(8, array_sum(array_map(fn ($item) => $item->net_amount, array_filter($order->items, fn ($item) => $item->subscription_id === '01JSUB'))));
        $this->assertSame('SUMMER20', $order->items[1]->discount->label);
        $this->assertSame('RE-2026-0001', $order->invoice->number);
    }

    public function test_the_invoice_pdf_is_downloaded_with_the_app_token(): void
    {
        $this->http->append(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.7 invoice'));
        $this->respond(200, ['data' => ['id' => 'o1', 'user_id' => '01JUSER']]);
        $this->http->append(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.7 invoice'));

        $this->assertSame('%PDF-1.7 invoice', app(AnikeenId::class)->appOrders()->invoicePdf('o1'));
        $this->assertSame('%PDF-1.7 invoice', $this->owner()->appOrders()->invoicePdf('o1'));

        $calls = array_map(fn ($entry) => $entry['request']->getMethod() . ' ' . $entry['request']->getUri()->getPath() . ' ' . $entry['request']->getHeaderLine('Authorization'), $this->sent);
        $this->assertSame([
            'GET /api/v1/client/orders/o1/invoice Bearer app-token',
            'GET /api/v1/client/orders/o1 Bearer app-token',
            'GET /api/v1/client/orders/o1/invoice Bearer app-token',
        ], $calls);
    }

    public function test_an_invoice_that_is_not_ready_is_a_conflict(): void
    {
        $this->respond(409, ['message' => 'The invoice document is still being created.', 'error' => 'invoice_not_ready']);
        $this->respond(200, ['data' => ['id' => 'o1', 'user_id' => 'someone-else']]);

        try {
            app(AnikeenId::class)->appOrders()->invoicePdf('o1');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isConflict());
            $this->assertSame('invoice_not_ready', $e->error);
        }

        try {
            $this->owner()->appOrders()->invoicePdf('o1');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isNotFound());
            $this->assertSame('order_not_found', $e->error);
        }
    }
}
