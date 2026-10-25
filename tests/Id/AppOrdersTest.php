<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
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
}
