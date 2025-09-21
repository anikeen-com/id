<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Orders;
use Throwable;

trait ManagesOrders
{
    use HasBillable;

    /**
     * Get orders from the current user.
     *
     * @throws Throwable
     */
    public function orders(array $parameters = []): Orders
    {
        if (!isset($this->ordersCache)) {
            $this->ordersCache = Orders::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/orders', [], $parameters))
                ->setBillable($this);
        }

        return $this->ordersCache;
    }
}