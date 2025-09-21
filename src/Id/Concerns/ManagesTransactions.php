<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Transactions;
use Throwable;

trait ManagesTransactions
{
    use HasBillable;

    /**
     * Get transactions from the current user.
     *
     * @throws Throwable
     */
    public function transactions(): Transactions
    {
        if (!isset($this->transactionsCache)) {
            $this->transactionsCache = Transactions::builder(fn() => $this->anikeenId()
                ->request('GET', 'v1/transactions'))
                ->setBillable($this);
        }

        return $this->transactionsCache;
    }
}