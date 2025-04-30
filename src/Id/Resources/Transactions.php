<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class Transactions extends BaseCollection
{
    use HasBillable;

    /**
     * Create a new transaction for the current user.
     *
     * @param array $attributes The attributes for the transaction.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     * @todo Add type hinting for the attributes array.
     */
    public function create(array $attributes = []): Transaction
    {
        return new Transaction($this->billable->request('POST', 'v1/transactions', $attributes));
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Transaction
    {
        $result = $this->billable->request('GET', sprintf('v1/transactions/%s', $id));

        return $result->success()
            ? (new Transaction($result))
                ->setBillable($this->billable)
            : null;
    }
}