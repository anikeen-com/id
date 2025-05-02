<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Subscriptions;
use Throwable;

trait ManagesSubscriptions
{
    use Request;

    /**
     * Get subscriptions from the current user.
     *
     * @throws Throwable
     */
    public function subscriptions(): Subscriptions
    {
        if (!isset($this->subscriptionsCache)) {
            $this->subscriptionsCache = Subscriptions::builder(fn() => $this->request('GET', 'v1/subscriptions'))
                ->setBillable($this);
        }

        return $this->subscriptionsCache;
    }
}