<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class PaymentMethods extends BaseCollection
{
    use HasBillable;

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
        return (new PaymentMethod($this->billable->request('GET', 'v1/payment-methods/default')))
            ->setBillable($this->billable);
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