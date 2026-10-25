<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\AppSubscriptions;
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
}