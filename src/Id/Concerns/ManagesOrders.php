<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
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
    public function orders(): Result
    {
        return $this->request('GET', 'v1/orders');
    }

    /**
     * Creates a new order for the current user.
     *
     * VAT is calculated based on the billing address and shown in the order response.
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
    public function createOrder(array $attributes = []): Result
    {
        return $this->request('POST', 'v1/orders', $attributes);
    }

    /**
     * Get given order from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function order(string $orderId): Result
    {
        return $this->request('GET', sprintf('v1/orders/%s', $orderId));
    }

    /**
     * Update given order from the current user.
     *
     * VAT is calculated based on the billing address and shown in the order response.
     *
     * @param string $orderId The order ID.
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
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function updateOrder(string $orderId, array $attributes = []): Result
    {
        return $this->request('PUT', sprintf('v1/orders/%s', $orderId), $attributes);
    }

    /**
     * Checkout given order from the current user.
     *
     * @param string $orderId The order ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function checkoutOrder(string $orderId): Result
    {
        return $this->request('PUT', sprintf('v1/orders/%s/checkout', $orderId));
    }

    /**
     * Revoke given order from the current user.
     *
     * @param string $orderId The order ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function revokeOrder(string $orderId): Result
    {
        return $this->request('PUT', sprintf('v1/orders/%s/revoke', $orderId));
    }

    /**
     * Delete given order from the current user.
     *
     * @param string $orderId The order ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function deleteOrder(string $orderId): Result
    {
        return $this->request('DELETE', sprintf('v1/orders/%s', $orderId));
    }

    /**
     * Get order items from given order.
     *
     * @param string $orderId The order ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function orderItems(string $orderId): Result
    {
        return $this->request('GET', sprintf('v1/orders/%s/items', $orderId));
    }

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
    public function createOrderItem(string $orderId, array $attributes = []): Result
    {
        return $this->request('POST', sprintf('v1/orders/%s', $orderId), $attributes);
    }

    /**
     * Get given order item from given order.
     *
     * @param string $orderId The order ID.
     * @param string $orderItemId The order item ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function orderItem(string $orderId, string $orderItemId): Result
    {
        return $this->request('GET', sprintf('v1/orders/%s/items/%s', $orderId, $orderItemId));
    }

    /**
     * Update given order item from given order.
     *
     * VAT is calculated based on the billing address and shown in the order item response.
     *
     * @param string $orderId The order ID.
     * @param string $orderItemId The order item ID.
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
    public function updateOrderItem(string $orderId, string $orderItemId, array $attributes = []): Result
    {
        return $this->request('PUT', sprintf('v1/orders/%s/items/%s', $orderId, $orderItemId), $attributes);
    }

    /**
     * Delete given order item from given order.
     *
     * @param string $orderId The order ID.
     * @param string $orderItemId The order item ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function deleteOrderItem(string $orderId, string $orderItemId): Result
    {
        return $this->request('DELETE', sprintf('v1/orders/%s/items/%s', $orderId, $orderItemId));
    }
}