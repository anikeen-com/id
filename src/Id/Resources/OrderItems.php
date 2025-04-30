<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Concerns\HasParent;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class OrderItems extends BaseCollection
{
    use HasBillable;
    use HasParent;

    /**
     * Create a new order item for given order.
     *
     * VAT is calculated based on the billing address and shown in the order item response.
     *
     * @param string $orderId The order ID.
     * @param array{
     *      items: array<array{
     *          type:        string,
     *          name:        string,
     *          description: string,
     *          price:       float|int,
     *          unit:        string,
     *          units:       int
     *      }>
     *  } $attributes The order data:
     *    - items:           Array of order items, each with type, name, description, price, unit, and quantity
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function create(string $orderId, array $attributes = []): OrderItem
    {
        return (new OrderItem($this->billable->request('POST', sprintf('v1/orders/%s', $orderId), $attributes)))
            ->setBillable($this->billable)
            ->setParent($this->parent);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?OrderItem
    {
        /** @var Result $result */
        $result = $this->parent->request('GET', sprintf('v1/orders/%s/items/%s', $this->parent->id, $id));

        return $result->success() ?
            (new OrderItem($result))
                ->setBillable($this->billable)
                ->setParent($this->parent)
            : null;
    }
}