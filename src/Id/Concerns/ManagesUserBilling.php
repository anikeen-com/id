<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\AppOrders;
use Anikeen\Id\Result;
use Throwable;

/**
 * Orders and payments on behalf of a user. Trusted apps only, sent with the app's token.
 */
trait ManagesUserBilling
{
    /**
     * Orders of all users that bill the app's subscriptions (e.g. ->pending() for unpaid ones).
     */
    public function appOrders(): AppOrders
    {
        return new AppOrders();
    }

    /**
     * Create an order for a user (VAT from the billing address). With `checkout`, it is paid right
     * away: with the given `transaction` (a payment captured elsewhere) or by charging the user's
     * default payment method (402 when declined; the order stays pending and is retried).
     *
     * @param string $userId The user's Anikeen ID.
     * @param array{
     *     group?: string,
     *     billing_address_id?: string,
     *     billing_address?: array,
     *     shipping_address_id?: string,
     *     shipping_address?: array,
     *     items: array<array{type: string, name: string, description?: string, price?: float|string, gross_price?: float|string, unit: string, units: int}>,
     *     checkout?: bool,
     *     transaction_id?: string,
     *     transaction?: array,
     *     invoice_date?: string,
     *     currency_iso?: string
     * } $attributes
     * @throws Throwable
     */
    public function createOrderForUser(string $userId, array $attributes): Result
    {
        return $this->asApp()->request('POST', 'v1/admin/orders', [...$attributes, 'user_id' => $userId]);
    }

    /**
     * Book a payment of a user that was captured elsewhere (e.g. PayPal, Ko-fi). The same
     * provider/payment_intent can only be booked once (409 with the existing transaction_id).
     *
     * @param string $userId The user's Anikeen ID.
     * @param array{
     *     payment_provider?: string,
     *     payment_intent?: string,
     *     status: string,
     *     type: string,
     *     amount: float|string,
     *     currency_iso: string,
     *     created_at: string,
     *     group?: string,
     *     invoice_id?: string
     * } $attributes
     * @throws Throwable
     */
    public function createTransactionForUser(string $userId, array $attributes): Result
    {
        return $this->asApp()->request('POST', 'v1/admin/transactions', [...$attributes, 'user_id' => $userId]);
    }
}
