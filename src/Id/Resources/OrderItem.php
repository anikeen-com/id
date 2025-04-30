<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Concerns\HasParent;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @property string $id
 */
class OrderItem extends BaseResource
{
    use HasBillable;
    use HasParent;

    /**
     * Update given order item from given order.
     *
     * VAT is calculated based on the billing address and shown in the order item response.
     *
     * @param array{
     *       items: array<array{
     *           type:        string,
     *           name:        string,
     *           description: string,
     *           price:       float|int,
     *           unit:        string,
     *           units:       int
     *       }>
     *   } $attributes The order data:
     *     - items:           Array of order items, each with type, name, description, price, unit, and quantity
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function update(array $attributes = []): self
    {
        return (new self($this->billable->request('PUT', sprintf('v1/orders/%s/items/%s', $this->parent->id, $this->id), $attributes)))
            ->setBillable($this->billable)
            ->setParent($this->parent);
    }

    /**
     * Delete given order item from given order.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function delete(): bool
    {
        return $this->billable->request('DELETE', sprintf('v1/orders/%s/items/%s', $this->parent->id, $this->id))->success();
    }
}