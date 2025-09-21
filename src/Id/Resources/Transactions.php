<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;

class Transactions extends BaseCollection
{
    use HasBillable;

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Transaction
    {
        return (new Transaction(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/transactions/%s', $id))))
            ->setBillable($this->billable);
    }
}