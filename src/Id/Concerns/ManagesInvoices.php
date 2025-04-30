<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Invoices;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesInvoices
{
    use Request;

    /**
     * Get invoices from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function invoices(array $parameters = []): Invoices
    {
        return (new Invoices($this->request('GET', 'v1/invoices', [], $parameters)))
            ->setBillable($this);
    }
}