<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Resources\AppCheckoutSessions;
use Anikeen\Id\Resources\AppOrders;
use Anikeen\Id\Resources\AppSubscriptions;
use Anikeen\Id\Resources\CheckoutSessions;
use Anikeen\Id\Resources\Subscriptions;
use Throwable;

trait ManagesSubscriptions
{
    use HasBillable;

    /**
     * Get subscriptions from the current user.
     *
     * @throws Throwable
     */
    public function subscriptions(): Subscriptions
    {
        if (!isset($this->subscriptionsCache)) {
            $this->subscriptionsCache = Subscriptions::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/subscriptions'))
                ->setBillable($this);
        }

        return $this->subscriptionsCache;
    }

    /**
     * The user's subscriptions, managed with the app's own token instead of the user's
     * (trusted apps only, scope subscriptions:client). Use this in jobs and anywhere the user's
     * login may have expired.
     */
    public function appSubscriptions(): AppSubscriptions
    {
        return new AppSubscriptions($this);
    }

    /**
     * Checkout sessions for the user, created with the app's own token (trusted apps only, scope
     * subscriptions:client): the user confirms several subscriptions at once on Anikeen ID.
     */
    public function appCheckoutSessions(): AppCheckoutSessions
    {
        return new AppCheckoutSessions($this);
    }

    /**
     * Checkout sessions with the user's own access token (any app): the user confirms several
     * subscriptions at once on Anikeen ID.
     */
    public function checkoutSessions(): CheckoutSessions
    {
        return new CheckoutSessions($this);
    }

    /**
     * The user's orders that bill the app's subscriptions, read with the app's own token
     * (trusted apps only, scope orders:client), e.g. ->pending() for unpaid ones.
     */
    public function appOrders(): AppOrders
    {
        return new AppOrders($this);
    }

    /**
     * A link to the user's billing pages on Anikeen ID that needs no login, created with the app's
     * own token (trusted apps only, scope subscriptions:client or orders:client), e.g. for a
     * "Manage billing" button; see AnikeenId::createBillingPortalLinkForUser() for the attributes
     * (return_url, group, flow). Single use and valid for about 15 minutes: create it when the
     * user clicks and redirect right away.
     *
     * @param array{return_url?: null|string, group?: null|string, flow?: null|string} $attributes
     *
     * @throws ApiException
     */
    public function appBillingPortalUrl(array $attributes = []): string
    {
        return app(AnikeenId::class)->createBillingPortalLinkForUser($this->appSubscriptions()->userId(), $attributes)->url;
    }
}
