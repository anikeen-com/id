<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Throwable;

trait ManagesTaxation
{
    use Request;

    /**
     * Get VAT for the current user.
     *
     * @throws Throwable
     */
    public function vatRate(): float
    {
        return $this->getUserData()->vat_rate;
    }
}