<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Invoices;
use Throwable;

trait ManagesInvoices
{
    use Request;

    /**
     * Get invoices from the current user.
     *
     * @throws Throwable
     */
    public function invoices(array $parameters = []): Invoices
    {
        if (!isset($this->invoicesCache)) {
            $this->invoicesCache = Invoices::builder(fn() => $this->request('GET', 'v1/invoices', [], $parameters))
                ->setBillable($this);
        }

        return $this->invoicesCache;
    }
}