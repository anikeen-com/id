<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Addresses;
use Throwable;

trait ManagesAddresses
{
    use HasBillable;

    /**
     * Get addresses from the current user.
     *
     * @throws Throwable
     */
    public function addresses(): Addresses
    {
        if (!isset($this->addressesCache)) {
            $this->addressesCache = Addresses::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/addresses'))
                ->setBillable($this);
        }

        return $this->addressesCache;
    }

    /**
     * Check if the current user has a default billing address.
     *
     * @throws Throwable
     * @see \Anikeen\Id\Resources\Addresses::hasDefaultBillingAddress()
     */
    public function hasDefaultBillingAddress(): bool
    {
        return $this->addresses()->hasDefaultBillingAddress();
    }
}