<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Orders;
use Anikeen\Id\Result;
use Throwable;

trait ManagesOrders
{
    use Request;

    /**
     * Get orders from the current user.
     *
     * @throws Throwable
     */
    public function orders(array $parameters = []): Orders
    {
        if (!isset($this->ordersCache)) {
            $this->ordersCache = Orders::builder(fn() => $this->request('GET', 'v1/orders', [], $parameters))
                ->setBillable($this);
        }

        return $this->ordersCache;
    }
}