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
     *     calculate_shipping?: bool,
     *     shipping_method_id?: null|string,
     *     free_shipping?: bool,
     *     free_shipping_countries?: null|list<string>,
     *     free_shipping_excluded_postal_codes?: null|array<string, list<string>>,
     *     shipping_countries?: null|list<string>,
     *     shipping_excluded_postal_codes?: null|array<string, list<string>>,
     *     ship_after?: null|string,
     *     return_url?: null|string,
     *     metadata?: null|array,
     *     items: array<array{
     *         product_id?:  null|string,
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
     *   - calculate_shipping: Anikeen ID adds the shipping from the app's shipping zones and the shipping address; send no shipping item then (optional)
     *   - shipping_method_id: The method of the matching zone the customer picked (optional, default: the zone's first; changeable on the confirmation page)
     *   - free_shipping:    The app grants the shipping, e.g. with a membership: the zone's methods cost nothing (with calculate_shipping; optional)
     *   - free_shipping_countries: ISO codes of the delivery countries free_shipping and ship_after apply to, e.g. ['DE'];
     *                       elsewhere the zone's normal price applies and nothing is held (optional, default: everywhere)
     *   - free_shipping_excluded_postal_codes: Postal codes left out per country, e.g. ['DE' => ['27498', '78266']]
     *                       (zone patterns: exact, `26*`, `25938...25999`; optional)
     *   - shipping_countries: ISO codes of the countries the order is delivered to at all, e.g. ['DE']; to another address
     *                       it can't be paid (checkout: 409 `shipping_unavailable`, nothing created; optional, default: everywhere)
     *   - shipping_excluded_postal_codes: Postal codes left out of shipping_countries, e.g. ['DE' => ['27498', '78266']] (optional)
     *   - ship_after:       Date (Y-m-d) until which the warehouse holds the goods back, e.g. to send a customer's orders
     *                       together in one parcel; today up to 3 months ahead (optional)
     *   - return_url:       Where the user returns after confirming (same origin as one of the app's redirect URIs; optional)
     *   - metadata:         The app's own data (max. 50 keys), returned as is in the API and webhooks (optional)
     *   - items:            Array of order items:
     *       - product_id:  A product of the app's catalog (AnikeenId::inventory()): the item takes what it
     *                      leaves out (name, type, unit, price, picture) from it, and its tracked stock is
     *                      reserved; 409 "out_of_stock" (ApiException::isOutOfStock(), details in body()) when too few are left
     *                      A price-bound product (Buchpreisbindung) is only sold at its catalog price: leave the
     *                      price out or send exactly that; another one gives 422 "price_changed"
     *                      (ApiException::isPriceChanged(), the catalog prices in body())
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