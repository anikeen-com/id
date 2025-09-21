<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Result;
use Throwable;

trait ManagesPricing
{
    use Post;

    /**
     * Make a new order preview (will not be stored into the database).
     *
     * VAT is calculated based on the billing address and shown in the order response.
     *
     * @param array{
     *     country_iso: string,
     *     items: array<array{
     *         type:        string,
     *         name:        string,
     *         description: string,
     *         price:       float|int,
     *         unit:        string,
     *         units:       int
     *     }>
     * } $attributes The order data:
     *   - country_iso:     ISO 3166-1 alpha-2 country code
     *   - items:           Array of order items (each with type, name, price, unit, units, and quantity)
     * @throws Throwable
     */
    public function createOrderPreview(array $attributes = []): Result
    {
        return $this->post('v1/orders/preview', $attributes);
    }
}