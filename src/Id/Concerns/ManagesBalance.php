<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\Transaction;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesBalance
{
    use Request;

    /**
     * Get balance from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function balance(): float
    {
        return $this->getUserData()->current_balance;
    }

    /**
     * Get charges from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function charges(): float
    {
        return $this->getUserData()->current_charges;
    }

    /**
     * Charge given amount from bank to current user.
     *
     * @param float $amount Amount to charge in euros.
     * @param string $paymentMethodId Payment method ID.
     * @param array $options Additional options for the charge.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function charge(float $amount, string $paymentMethodId, array $options = []): Transaction
    {
        return new Transaction($this->request('POST', 'billing/charge', [
            'amount' => $amount,
            'payment_method_id' => $paymentMethodId,
            'options' => $options,
        ]));
    }
}