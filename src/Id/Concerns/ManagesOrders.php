<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Orders;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesOrders
{
    use Request;

    /**
     * Get orders from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function orders(array $parameters = []): Orders
    {
        return (new Orders($this->request('GET', 'v1/orders', [], $parameters)))
            ->setBillable($this);
    }
}