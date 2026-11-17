<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Illuminate\Database\Eloquent\Model;

class OrderNumbersTest extends TestCase
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

    private function page(array $orders): array
    {
        return ['data' => $orders, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($orders)], 'links' => ['next' => null]];
    }

    private function requestQuery(int $index): array
    {
        parse_str($this->sent[$index]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    public function test_an_app_lists_its_own_orders_of_the_user_with_their_numbers(): void
    {
        $this->respond(200, $this->page([['id' => 'o2', 'order_number' => 'B2026-0002']]));
        $this->respond(200, $this->page([['id' => 'o1', 'order_number' => 'B2026-0001'], ['id' => 'o2', 'order_number' => 'B2026-0002']]));

        $owner = $this->owner();
        $own = $owner->orders(['own' => true])->paginate()->data;
        $all = $owner->orders()->paginate()->data;

        $this->assertSame(['B2026-0002'], array_column($own, 'order_number'));
        $this->assertCount(2, $all);
        $this->assertSame('/api/v1/orders', $this->sent[0]['request']->getUri()->getPath());
        $this->assertSame('1', $this->requestQuery(0)['own']);
        $this->assertSame([], $this->requestQuery(1), 'other parameters are a separate list, not the cached one');
    }

    public function test_an_order_is_found_by_its_number(): void
    {
        $this->respond(200, $this->page([['id' => 'o1', 'order_number' => 'B2026-0001', 'user_id' => '01JUSER']]));
        $this->respond(200, $this->page([]));

        $this->assertSame('o1', app(AnikeenId::class)->appOrders()->findByNumber('B2026-0001')->id);
        $this->assertNull($this->owner()->appOrders()->findByNumber('B2026-9999'));
        $this->assertSame('B2026-0001', $this->requestQuery(0)['order_number']);
        $this->assertSame(['order_number' => 'B2026-9999', 'user_id' => '01JUSER'], array_intersect_key($this->requestQuery(1), ['order_number' => 1, 'user_id' => 1]));
    }
}
