<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Transactions;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesTransactions
{
    use Request;

    /**
     * Get transactions from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function transactions(): Transactions
    {
        return (new Transactions($this->request('GET', 'v1/transactions')))
            ->setBillable($this);;
    }
}