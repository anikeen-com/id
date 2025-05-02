<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Countries;
use Throwable;

trait ManagesCountries
{
    use Request;

    /**
     * Get available countries for the current user.
     *
     * @throws Throwable
     */
    public function countries(): Countries
    {
        if (!isset($this->countriesCache)) {
            $this->countriesCache = Countries::builder(fn() => $this->request('GET', 'v1/countries'))
                ->setBillable($this);
        }

        return $this->countriesCache;
    }
}