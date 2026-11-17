<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Orders;
use Throwable;

trait ManagesOrders
{
    use HasBillable;

    /**
     * Get orders from the current user, newest first.
     *
     * @param array{own?: bool, order_number?: string, group?: string, collective?: bool, page?: int} $parameters
     *        `own`: only the orders the calling app created (e.g. a shop listing its orders);
     *        `order_number`: the order with this readable number (B2026-0001)
     *
     * @throws Throwable
     */
    public function orders(array $parameters = []): Orders
    {
        $key = json_encode($parameters);

        if (!isset($this->ordersCache[$key])) {
            $this->ordersCache[$key] = Orders::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/orders', [], $parameters))
                ->setBillable($this);
        }

        return $this->ordersCache[$key];
    }
}