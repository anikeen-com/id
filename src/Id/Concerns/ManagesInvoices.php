<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Invoices;
use Throwable;

trait ManagesInvoices
{
    use HasBillable;

    /**
     * Get invoices from the current user.
     *
     * @throws Throwable
     */
    public function invoices(array $parameters = []): Invoices
    {
        if (!isset($this->invoicesCache)) {
            $this->invoicesCache = Invoices::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/invoices', [], $parameters))
                ->setBillable($this);
        }

        return $this->invoicesCache;
    }
}