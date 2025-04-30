<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class Orders extends BaseCollection
{
    use HasBillable;

    /**
     * Creates a new order for the current user.
     *
     * VAT is calculated based on the billing address and shown in the order response.
     *
     * The billing and shipping addresses are each persisted as standalone Address entities
     * in the database, but are also embedded (deep-copied) into the Order object itself
     * rather than merely referenced. This guarantees that the order retains its own snapshot
     * of both addresses for future reference.
     *
     * @param array{
     *     billing_address: array{
     *          company_name: null|string,
     *          first_name:  null|string,
     *          last_name: null|string,
     *          address: null|string,
     *          address_2: null|string,
     *          house_number: null|string,
     *          city: null|string,
     *          state: null|string,
     *          postal_code: null|string,
     *          country_iso: string,
     *          phone_number: null|string,
     *          email: null|string
     *     },
     *     shipping_address: null|array{
     *          company_name: null|string,
     *          first_name:  string,
     *          last_name: string,
     *          address: null|string,
     *          address_2: string,
     *          house_number: null|string,
     *          city: string,
     *          state: string,
     *          postal_code: string,
     *          country_iso: string,
     *          phone_number: null|string,
     *          email: null|string
     *     },
     *     items: array<array{
     *         type:        string,
     *         name:        string,
     *         description: string,
     *         price:       float|int,
     *         unit:        string,
     *         units:       int
     *     }>
     * } $attributes The order data:
     *   - billing_address:  Billing address (ISO 3166-1 alpha-2 country code)
     *   - shipping_address: Shipping address (first name, last name, ISO 3166-1 alpha-2 country code)
     *   - items:            Array of order items (each with type, name, price, unit, units, and quantity)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function create(array $attributes = []): Order
    {
        return (new Order($this->billable->request('POST', 'v1/orders', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Get given order from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function find(string $id): ?Order
    {
        /** @var Result $result */
        $result = $this->billable->request('GET', sprintf('v1/orders/%s', $id));

        return $result->success()
            ? (new Order($result))
                ->setBillable($this->billable)
            : null;
    }
}