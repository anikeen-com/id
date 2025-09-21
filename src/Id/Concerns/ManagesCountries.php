<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Countries;
use Throwable;

trait ManagesCountries
{
    use HasBillable;

    /**
     * Get available countries for the current user.
     *
     * @throws Throwable
     */
    public function countries(): Countries
    {
        if (!isset($this->countriesCache)) {
            $this->countriesCache = Countries::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/countries'))
                ->setBillable($this);
        }

        return $this->countriesCache;
    }
}