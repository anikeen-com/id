<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesTaxation
{
    use Request;

    /**
     * Get VAT for the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function vat(): float
    {
        return $this->getUserData()->vat;
    }
}