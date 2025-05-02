<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Throwable;

class PaymentMethods extends BaseCollection
{
    use HasBillable;

    /**
     * Check if current user has at least one payment method.
     *
     * @throws Throwable
     */
    public function hasPaymentMethod(): bool
    {
        return $this->result->count() > 0;
    }

    /**
     * Check if the current user has a default payment method.
     *
     * @throws Throwable
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return $this->defaultPaymentMethod()?->id !== null;
    }

    /**
     * Get default payment method from the current user.
     *
     * @throws Throwable
     */
    public function defaultPaymentMethod(): PaymentMethod
    {
        if (!isset($this->defaultPaymentMethodCache)) {
            $this->defaultPaymentMethodCache = (new PaymentMethod(fn() => $this->billable->request('GET', 'v1/payment-methods/default')))
                ->setBillable($this->billable);
        }

        return $this->defaultPaymentMethodCache;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?PaymentMethod
    {
        return (new PaymentMethod(fn() => $this->billable->request('GET', sprintf('v1/payment-methods/%s', $id))))
            ->setBillable($this->billable);
    }
}