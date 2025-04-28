<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesPaymentMethods
{
    use Request;

    /**
     * Check if current user has at least one payment method.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasPaymentMethod(): bool
    {
        return $this->paymentMethods()->count() > 0;
    }

    /**
     * Get payment methods from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function paymentMethods(): Result
    {
        return $this->request('GET', 'v1/payment-methods');
    }

    /**
     * Get default payment method from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return (bool)$this->defaultPaymentMethod()->data;
    }

    /**
     * Get default payment method from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function defaultPaymentMethod(): Result
    {
        return $this->request('GET', 'v1/payment-methods/default');
    }

    /**
     * Get billing portal URL for the current user.
     *
     * @param string $returnUrl The URL to redirect to after the user has finished in the billing portal.
     * @param array $options Additional options for the billing portal.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function billingPortalUrl(string $returnUrl, array $options): string
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
