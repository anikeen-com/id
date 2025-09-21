<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\Transaction;
use Throwable;

trait ManagesBalance
{
    use HasBillable;

    /**
     * Get balance from the current user.
     *
     * @throws Throwable
     */
    public function balance(): float
    {
        return $this->getUserData()->current_balance;
    }

    /**
     * Get charges from the current user.
     *
     * @throws Throwable
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
     * @throws Throwable
     */
    public function charge(float $amount, string $paymentMethodId, array $options = []): Transaction
    {
        return (new Transaction(fn() => $this->anikeenId()
            ->request('POST', 'billing/charge', [
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
                'options' => $options,
            ])))
            ->setBillable($this);
    }
}