<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;

class Invoices extends BaseCollection
{
    use HasBillable;

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Invoice
    {
        return (new Invoice(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/invoices/%s', $id))))
            ->setBillable($this->billable);
    }
}