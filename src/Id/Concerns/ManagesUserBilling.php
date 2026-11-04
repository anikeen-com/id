<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Resources\AppCheckoutSessions;
use Anikeen\Id\Resources\AppOrders;
use Anikeen\Id\Result;
use Anikeen\Id\Support\AppRequest;
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
     * A short-lived link to a user's billing pages on Anikeen ID, e.g. for a "Manage billing"
     * button of an app that bills another user's account (a team owner) without that user's
     * token (trusted apps, scope subscriptions:client or orders:client). The link is signed and
     * valid for about 15 minutes: create it when the user clicks, don't store it. It doesn't sign
     * anyone in; the pages then show only what bills the app's subscriptions (of the group) and
     * lead back to return_url.
     *
     * @param string $userId The user's Anikeen ID.
     * @param array{
     *     return_url?: null|string,
     *     group?: null|string,
     *     flow?: null|string
     * } $attributes return_url: "Back to the app" (same origin as one of the app's redirect URIs,
     *               422 otherwise).
     *               group: only this group's subscriptions and the orders, invoices and
     *               transactions billing them.
     *               flow: overview (default), payment_method, payment_method_update (straight to
     *               replacing the payment method, e.g. after a failed payment), invoices,
     *               subscriptions or orders.
     * @return object {object: "billing_portal_link", url, user_id, flow, group, return_url, expires_at}
     *
     * @throws ApiException 403 untrusted app or missing scope, 404 unknown user, 422 validation
     */
    public function createBillingPortalLinkForUser(string $userId, array $attributes = []): object
    {
        return AppRequest::send('POST', sprintf('v1/client/users/%s/billing-portal', $userId), $attributes)->data;
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
