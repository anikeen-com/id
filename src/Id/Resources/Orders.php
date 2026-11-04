<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Throwable;

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
     *          first_name: string,
     *          last_name: string,
     *          address: string,
     *          address_2: null|string,
     *          house_number: null|string,
     *          city: string,
     *          state: null|string,
     *          postal_code: string,
     *          country_iso: string,
     *          phone_number: null|string,
     *          email: null|string
     *     },
     *     group?: null|string,
     *     confirm?: bool,
     *     return_url?: null|string,
     *     metadata?: null|array,
     *     items: array<array{
     *         type:         string,
     *         name:         string,
     *         description?: null|string,
     *         image_url?:   null|string,
     *         price?:       float|int|string,
     *         gross_price?: float|int|string,
     *         unit:         string,
     *         units:        int
     *     }>
     * } $attributes The order data:
     *   - billing_address:  Billing address (ISO 3166-1 alpha-2 country code)
     *   - shipping_address: Shipping address for physical items (first name, last name, address, postal code, city, ISO 3166-1 alpha-2 country code)
     *   - group:            The app's group, e.g. a team (optional)
     *   - confirm:          Trusted apps: leave confirming and paying to the user (confirmation_url, expires after 24 hours; optional)
     *   - return_url:       Where the user returns after confirming (same origin as one of the app's redirect URIs; optional)
     *   - metadata:         The app's own data (max. 50 keys), returned as is in the API and webhooks (optional)
     *   - items:            Array of order items:
     *       - type:        digital, physical or physical-reduced (VAT category)
     *       - price/gross_price: net or gross unit price, one of both
     *       - unit:        hour, day, week, month, year, onetime or piece
     *       - image_url:   http(s) URL of a product image; Anikeen ID copies it, so it stays
     *                      visible on the order and invoice when the shop removes it later
     * @throws Throwable
     */
    public function create(array $attributes = []): Order
    {
        return (new Order(fn() => $this->billable->anikeenId()
            ->request('POST', 'v1/orders', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Get given order from the current user.
     *
     * @throws Throwable
     */
    public function find(string $id): ?Order
    {
        return (new Order(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/orders/%s', $id))))
            ->setBillable($this->billable);
    }
}