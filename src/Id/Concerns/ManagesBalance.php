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
     * Billing overview of the current user: current_balance, current_charges, vat_rate,
     * has_billing_address, has_default_payment_method, open_orders.
     *
     * @throws Throwable
     */
    public function billingOverview(): object
    {
        return $this->anikeenId()->request('GET', 'v1/billing')->data;
    }

    /**
     * Charge given amount (deposit) from one of the current user's payment methods.
     *
     * @param float|string $amount Amount in euros (0.50 to 10,000, at most 2 decimals).
     * @param string $paymentMethodId Stripe payment method ID (pm_…).
     * @param array $options Ignored; Stripe options can no longer be passed through.
     * @throws Throwable
     */
    public function charge(float|string $amount, string $paymentMethodId, array $options = []): Transaction
    {
        return (new Transaction(fn() => $this->anikeenId()
            ->request('POST', 'v1/billing/charge', [
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
            ])))
            ->setBillable($this);
    }
}