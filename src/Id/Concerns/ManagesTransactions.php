<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Transactions;
use Anikeen\Id\Result;
use Throwable;

trait ManagesTransactions
{
    use Request;

    /**
     * Get transactions from the current user.
     *
     * @throws Throwable
     */
    public function transactions(): Transactions
    {
        if (!isset($this->transactionsCache)) {
            $this->transactionsCache = Transactions::builder(fn() => $this->request('GET', 'v1/transactions'))
                ->setBillable($this);
        }

        return $this->transactionsCache;
    }
}