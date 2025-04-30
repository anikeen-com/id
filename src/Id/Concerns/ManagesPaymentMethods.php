<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\PaymentMethod;
use Anikeen\Id\Resources\PaymentMethods;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesPaymentMethods
{
    use Request;

    /**
     * Get payment methods from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function paymentMethods(): PaymentMethods
    {
        return (new PaymentMethods($this->request('GET', 'v1/payment-methods')))
            ->setBillable($this);
    }

    /**
     * Check if current user has at least one payment method.
     *
     * @see \Anikeen\Id\Resources\PaymentMethods::hasPaymentMethod()
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->hasPaymentMethod();
    }

    /**
     * Get default payment method from the current user.
     *
     * @see \Anikeen\Id\Resources\PaymentMethods::defaultPaymentMethod()
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->defaultPaymentMethod();
    }

    /**
     * Check if the current user has a default payment method.
     *
     * @see \Anikeen\Id\Resources\PaymentMethods::hasDefaultPaymentMethod()
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return $this->paymentMethods()->hasDefaultPaymentMethod();
    }

    /**
     * Get billing portal URL for the current user.
     *
     * @param string|null $returnUrl The URL to redirect to after the user has finished in the billing portal.
     * @param array $options Additional options for the billing portal.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
    {
        return $this->request('POST', 'v1/billing/portal', [
            'return_url' => $returnUrl,
            'options' => $options,
        ])->data->url;
    }

    /**
     * Create a new setup intent.
     *
     * @param array $options Additional options for the setup intent.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function createSetupIntent(array $options = []): Result
    {
        return $this->request('POST', 'v1/payment-methods', [
            'options' => $options,
        ]);
    }
}
