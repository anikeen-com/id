<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\ResourceException;
use Throwable;

class Transactions extends BaseCollection
{
    use HasBillable;

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Transaction
    {
        return (new Transaction(fn() => $this->billable->request('GET', sprintf('v1/transactions/%s', $id))))
            ->setBillable($this->billable);
    }
}