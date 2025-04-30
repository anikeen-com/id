<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Addresses;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesAddresses
{
    use Request;

    /**
     * Get addresses from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function addresses(): Addresses
    {
        return (new Addresses($this->request('GET', 'v1/addresses')))
            ->setBillable($this);
    }
}