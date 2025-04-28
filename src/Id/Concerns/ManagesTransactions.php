<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
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
    public function transactions(): Result
    {
        return $this->request('GET', 'v1/transactions');
    }

    /**
     * Create a new transaction for the current user.
     *
     * @param array $attributes The attributes for the transaction.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     * @todo Add type hinting for the attributes array.
     */
    public function createTransaction(array $attributes = []): Result
    {
        return $this->request('POST', 'v1/transactions', $attributes);
    }

    /**
     * Get given transaction from current current user.
     *
     * @param string $transactionId The transaction ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function transaction(string $transactionId): Result
    {
        return $this->request('GET', sprintf('v1/transactions/%s', $transactionId));
    }
}