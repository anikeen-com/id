<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class PaymentMethods extends BaseCollection
{
    use HasBillable;

    private ?PaymentMethod $cachedDefaultPaymentMethod = null;

    /**
     * Check if current user has at least one payment method.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasPaymentMethod(): bool
    {
        return $this->result->count() > 0;
    }

    /**
     * Get default payment method from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function defaultPaymentMethod(): PaymentMethod
    {
        if ($this->cachedDefaultPaymentMethod === null) {
            $this->cachedDefaultPaymentMethod = (new PaymentMethod(
                $this->billable->request('GET', 'v1/payment-methods/default')
            ))->setBillable($this->billable);
        }

        return $this->cachedDefaultPaymentMethod;
    }

    /**
     * Check if the current user has a default payment method.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return $this->defaultPaymentMethod()?->id !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?PaymentMethod
    {
        $result = $this->billable->request('GET', sprintf('v1/payment-methods/%s', $id));

        return $result->success()
            ? (new PaymentMethod($result))
                ->setBillable($this->billable)
            : null;
    }
}