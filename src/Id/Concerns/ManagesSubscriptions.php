<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Subscriptions;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesSubscriptions
{
    use Request;

    /**
     * Get subscriptions from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function subscriptions(): Subscriptions
    {
        return (new Subscriptions($this->request('GET', 'v1/subscriptions')))
            ->setBillable($this);
    }
}