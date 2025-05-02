<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\ResourceException;
use Throwable;

class Transactions extends BaseCollection
{
    use HasBillable;

    /**
     * Create a new transaction for the current user.
     *
     * @param array $attributes The attributes for the transaction.
     * @throws Throwable
     * @todo Add type hinting for the attributes array.
     */
    public function create(array $attributes = []): Transaction
    {
        return (new Transaction($this->billable->request('POST', 'v1/transactions', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Transaction
    {
        return (new Transaction(fn() => $this->billable->request('GET', sprintf('v1/transactions/%s', $id))))
                ->setBillable($this->billable);
    }
}