<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Throwable;

/**
 * @property string $id
 */
class Order extends BaseResource
{
    use HasBillable;

    /**
     * Update given order from the current user.
     *
     * VAT is calculated based on the billing address and shown in the order response.
     *
     * The billing and shipping addresses are each persisted as standalone Address entities
     * in the database, but are also embedded (deep-copied) into the Order object itself
     * rather than merely referenced. This guarantees that the order retains its own snapshot
     * of both addresses for future reference.
     *
     * @param array{
     *      billing_address: array{
     *           company_name: null|string,
     *           first_name:  null|string,
     *           last_name: null|string,
     *           address: null|string,
     *           address_2: null|string,
     *           house_number: null|string,
     *           city: null|string,
     *           state: null|string,
     *           postal_code: null|string,
     *           country_iso: string,
     *           phone_number: null|string,
     *           email: null|string
     *      },
     *      shipping_address: null|array{
     *           company_name: null|string,
     *           first_name:  string,
     *           last_name: string,
     *           address: null|string,
     *           address_2: string,
     *           house_number: null|string,
     *           city: string,
     *           state: string,
     *           postal_code: string,
     *           country_iso: string,
     *           phone_number: null|string,
     *           email: null|string
     *      }
     *  } $attributes The order data:
     *    - billing_address:  Billing address (ISO 3166-1 alpha-2 country code)
     *    - shipping_address: Shipping address (first name, last name, ISO 3166-1 alpha-2 country code)
     * @throws Throwable
     */
    public function update(array $attributes = []): self
    {
        return (new self(fn() => $this->billable->request('PUT', sprintf('v1/orders/%s', $this->id), $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Checkout given order from the current user.
     *
     * @throws Throwable
     */
    public function checkout(): self
    {
        return (new self(fn() => $this->billable->request('PUT', sprintf('v1/orders/%s/checkout', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Revoke given order from the current user.
     *
     * @throws Throwable
     */
    public function revoke(): self
    {
        return (new self(fn() => $this->billable->request('PUT', sprintf('v1/orders/%s/revoke', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Delete given order from the current user.
     *
     * @throws Throwable
     */
    public function delete(): bool
    {
        return $this->billable->request('DELETE', sprintf('v1/orders/%s', $this->id))->success();
    }

    /**
     * Get order items from given order.
     *
     * @throws Throwable
     */
    public function orderItems(array $parameters = []): OrderItems
    {
        return OrderItems::builder(fn() => $this->billable->request('GET', sprintf('v1/orders/%s/items', $this->id), [], $parameters))
            ->setBillable($this->billable)
            ->setParent($this);
    }
}