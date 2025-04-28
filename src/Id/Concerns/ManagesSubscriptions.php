<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesSubscriptions
{
    use Request;

    /**
     * Get subscriptions from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function subscriptions(): Result
    {
        return $this->request('GET', 'v1/subscriptions');
    }

    /**
     * Get given subscription from the current user.
     *
     * @param string $subscriptionId The subscription ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function subscription(string $subscriptionId): Result
    {
        return $this->request('GET', sprintf('v1/subscriptions/%s', $subscriptionId));
    }

    /**
     * Create a new subscription for the current user.
     *
     * @param array{
     *      name: null,
     *      description: string,
     *      unit: string,
     *      price: float,
     *      vat: null|float,
     *      payload: null|array,
     *      ends_at: null|string,
     *      webhook_url: null|string,
     *      webhook_secret: null|string
     *  } $attributes The subscription data:
     *    - name:           The name
     *    - description:    The description
     *    - unit:           The unit (e.g. "hour", "day", "week", "month", "year")
     *    - price:          The price per unit
     *    - vat:            The VAT (optional)
     *    - payload:        The payload (optional)
     *    - ends_at:        The end date (optional)
     *    - webhook_url:     The webhook URL (optional)
     *    - webhook_secret:  The webhook secret (optional)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function createSubscription(array $attributes): Result
    {
        return $this->request('POST', 'v1/subscriptions', $attributes);
    }

    /**
     * Force given subscription to check out (trusted apps only).
     *
     * @param string $subscriptionId The subscription ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function checkoutSubscription(string $subscriptionId): Result
    {
        return $this->request('PUT', sprintf('v1/subscriptions/%s/checkout', $subscriptionId));
    }

    /**
     * Revoke a given running subscription from the current user.
     *
     * @param string $subscriptionId The subscription ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function revokeSubscription(string $subscriptionId): Result
    {
        return $this->request('PUT', sprintf('v1/subscriptions/%s/revoke', $subscriptionId));
    }

    /**
     * Resume a given running subscription from the current user.
     *
     * @param string $subscriptionId The subscription ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function resumeSubscription(string $subscriptionId): Result
    {
        return $this->request('PUT', sprintf('v1/subscriptions/%s/resume', $subscriptionId));
    }

    /**
     * Delete a given subscription from the current user.
     *
     * @param string $subscriptionId The subscription ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function deleteSubscription(string $subscriptionId): Result
    {
        return $this->request('DELETE', sprintf('v1/subscriptions/%s', $subscriptionId));
    }
}