<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Countries;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesCountries
{
    use Request;

    /**
     * Get available countries for the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function countries(): Countries
    {
        return (new Countries($this->request('GET', 'v1/countries')))
            ->setBillable($this);
    }
}