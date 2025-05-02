<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Addresses;
use Throwable;

trait ManagesAddresses
{
    use Request;

    /**
     * Get addresses from the current user.
     *
     * @throws Throwable
     */
    public function addresses(): Addresses
    {
        if (!isset($this->addressesCache)) {
            $this->addressesCache = Addresses::builder(fn() => $this->request('GET', 'v1/addresses'))
                ->setBillable($this);
        }

        return $this->addressesCache;
    }

    /**
     * Check if the current user has a default billing address.
     *
     * @see \Anikeen\Id\Resources\Addresses::hasDefaultBillingAddress()
     * @throws Throwable
     */
    public function hasDefaultBillingAddress(): bool
    {
        return $this->addresses()->hasDefaultBillingAddress();
    }
}