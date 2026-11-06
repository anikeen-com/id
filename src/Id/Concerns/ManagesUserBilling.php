<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Resources\AppCharge;
use Anikeen\Id\Resources\AppCheckoutSessions;
use Anikeen\Id\Resources\AppOrders;
use Anikeen\Id\Resources\BillingLimit;
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
     * A link to a user's billing pages on Anikeen ID that needs no login, like Stripe's customer
     * portal, e.g. for a "Manage billing" button of an app that bills another user's account (a
     * team owner) without that user's token (trusted apps, scope subscriptions:client or
     * orders:client). The link is single use and valid for about 15 minutes: create it when the
     * user clicks and redirect right away, don't store it. It opens a portal session for that user
     * only (no login, nobody signed in) with only the flow's billing pages and only what bills the
     * app's subscriptions (of the group), leading back to return_url; it ends after an hour
     * without activity.
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

    /**
     * Book a charge onto a user's open monthly bill of the app, e.g. a shipped item: one line,
     * no subscription, charged with the bill on the 1st. Trusted apps that Anikeen allowed to
     * charge (with a maximum per charge), for users connected to the app (they authorised it or
     * the app bills them). The user's billing limit is checked when it is booked.
     *
     * Retrying with the same idempotency key and the same fields returns the existing charge
     * (also after the limit was reached); other fields with that key: 409
     * "idempotency_key_conflict" (`charge_id` in body()).
     *
     * @param string $userId The user's Anikeen ID.
     * @param array{
     *     idempotency_key: string,
     *     name: string,
     *     description?: null|string,
     *     type?: string,
     *     quantity: int,
     *     unit_price: float|int|string,
     *     currency_iso?: string,
     *     group?: null|string,
     *     occurred_at?: string,
     *     reference?: null|string,
     *     metadata?: null|array<string, string>,
     *     image_url?: null|string
     * } $attributes idempotency_key: unique per app and user, ≤ 255 (e.g. "shipment-123").
     *               name: the line on the bill, ≤ 255. description: ≤ 255.
     *               type: digital (default), physical or physical-reduced (the VAT treatment).
     *               quantity: 1–10000 pieces. unit_price: net per piece, > 0, ≤ 100000, up to 6
     *               decimals; quantity × unit_price rounded to cents must be at least 0.01.
     *               currency_iso: EUR only (default).
     *               group: null, or a group in which the app already bills the user (a running
     *               subscription or an open monthly bill of the app).
     *               occurred_at: the service date, not in the future, at most 31 days ago
     *               (default now).
     *               reference: the app's own id (≤ 255). metadata: ≤ 20 keys (≤ 40 characters),
     *               string values ≤ 500. Both are not on the bill; put nothing sensitive there.
     *               image_url: http(s) URL of an image for the line.
     *
     * @throws ApiException 403 "client_not_allowed" (not a trusted app), "charges_not_enabled";
     *     404 "user_not_found" (unknown, deleted or not connected); 409 "billing_limit_reached"
     *     (isBillingLimitReached(); body() has limit, used, remaining, remaining_net, required,
     *     currency, resets_at), "charge_exceeds_maximum" (+ max_charge_net),
     *     "group_not_allowed", "idempotency_key_conflict" (+ charge_id),
     *     "billing_address_missing"; 422 validation; 429 "too_many_requests"
     */
    public function createChargeForUser(string $userId, array $attributes): AppCharge
    {
        return new AppCharge(AppRequest::send('POST', $this->chargesPath($userId), $attributes)->data);
    }

    /**
     * A charge of the app (or of a client delegated to the same app) for a user.
     *
     * @throws ApiException 403 "client_not_allowed", "charges_not_enabled"; 404 "user_not_found",
     *     "charge_not_found"; 429 "too_many_requests"
     */
    public function findChargeForUser(string $userId, string $chargeId): AppCharge
    {
        return new AppCharge(AppRequest::send('GET', $this->chargesPath($userId, $chargeId))->data);
    }

    /**
     * Void a charge while its bill is open: the line is removed and the bill's total drops. An
     * already voided charge is returned as it is. Once the bill is closed only a refund of the
     * order by Anikeen credits it.
     *
     * @param string|null $reason why (≤ 255), stored with the charge
     * @throws ApiException 409 "charge_already_billed" (+ charge_id); 404 "charge_not_found",
     *     "user_not_found"; 403 "client_not_allowed", "charges_not_enabled"; 429 "too_many_requests"
     */
    public function voidChargeForUser(string $userId, string $chargeId, ?string $reason = null): AppCharge
    {
        $payload = $reason === null ? null : ['reason' => $reason];

        return new AppCharge(AppRequest::send('POST', $this->chargesPath($userId, $chargeId) . '/void', $payload)->data);
    }

    /**
     * A user's monthly billing limit as the app sees it, e.g. to pause paid features while it is
     * reached. Advisory only: it reserves nothing; a charge is checked when it is booked. Trusted
     * apps that may charge or that Anikeen allowed to read limits, for users connected to the app.
     *
     * @throws ApiException 403 "client_not_allowed", "billing_limit_not_enabled"; 404
     *     "user_not_found"; 429 "too_many_requests"
     */
    public function billingLimitForUser(string $userId): BillingLimit
    {
        return new BillingLimit(AppRequest::send('GET', sprintf('v1/client/users/%s/billing-limit', rawurlencode($userId)))->data);
    }

    private function chargesPath(string $userId, ?string $chargeId = null): string
    {
        $path = sprintf('v1/client/users/%s/charges', rawurlencode($userId));

        return $chargeId === null ? $path : $path . '/' . rawurlencode($chargeId);
    }
}
