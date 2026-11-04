<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\AppCheckoutSessions;
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
     * Checkout sessions of the app for any user, e.g. to look one up from a checkout.* webhook
     * (create() then needs a user_id).
     */
    public function appCheckoutSessions(): AppCheckoutSessions
    {
        return new AppCheckoutSessions();
    }

    /**
     * Create an order for a user (VAT from the billing address). With `checkout`, it is paid right
     * away: with the given `transaction` (a payment captured elsewhere) or by charging the user's
     * default payment method (402 when declined; the order stays pending and is retried).
     *
     * With `confirm`, the user confirms and pays it on Anikeen ID instead (e.g. a top-up): the
     * order stays a draft with `confirmation_url` (send the user there) and is cancelled if not
     * confirmed within 24 hours (`expires_at`; afterwards checking it out answers 409
     * "confirmation_expired"). Webhooks: order.created, order.completed once paid, or
     * order.cancelled when the user declines or it expires.
     *
     * @param string $userId The user's Anikeen ID.
     * @param array{
     *     group?: string,
     *     billing_address_id?: string,
     *     billing_address?: array,
     *     shipping_address_id?: string,
     *     shipping_address?: array,
     *     items: array<array{type: string, name: string, description?: string, image_url?: string, price?: float|string, gross_price?: float|string, unit: string, units: int}>,
     *     checkout?: bool,
     *     confirm?: bool,
     *     return_url?: null|string,
     *     metadata?: null|array,
     *     transaction_id?: string,
     *     transaction?: array,
     *     invoice_date?: string,
     *     currency_iso?: string
     * } $attributes confirm: needs items, can't be combined with checkout or a transaction.
     *               return_url: where the user returns after confirming, with `status` and
     *               `order` (same origin as one of the app's redirect URIs, 422 otherwise).
     *               metadata: the app's own data (max. 50 keys), returned as is in the API and
     *               the order webhooks.
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
