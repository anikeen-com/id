<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @property string $id
 * @property string $name
 * @property string $description
 * @property string $unit
 * @property float $price
 * @property float $vat_rate
 * @property array $payload
 * @property string $ends_at
 * @property string $webhook_url
 * @property string $webhook_secret
 */
class Subscription extends BaseResource
{
    use HasBillable;

    /**
     * Update a given subscription from the current user.
     *
     * @param array{
     *      name: null,
     *      description: null|string,
     *      unit: string,
     *      price: float,
     *      vat_rate: null|float,
     *      payload: null|array,
     *      ends_at: null|string,
     *      webhook_url: null|string,
     *      webhook_secret: null|string
     *  } $attributes The subscription data:
     *    - name:           The name
     *    - description:    The description (optional)
     *    - unit:           The unit (e.g. "hour", "day", "week", "month", "year")
     *    - price:          The price per unit
     *    - vat_rate:       The VAT rate (optional)
     *    - payload:        The payload (optional)
     *    - ends_at:        The end date (optional)
     *    - webhook_url:    The webhook URL (optional)
     *    - webhook_secret: The webhook secret (optional)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function update(array $attributes): self
    {
        return (new self($this->billable->request('PUT', sprintf('v1/subscriptions/%s', $this->id), $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Force given subscription to check out (trusted apps only).
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function checkout(): self
    {
        return (new self($this->billable->request('PUT', sprintf('v1/subscriptions/%s/checkout', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Revoke a given running subscription from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function revoke(): self
    {
        return (new self($this->billable->request('PUT', sprintf('v1/subscriptions/%s/revoke', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Resume a given running subscription from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function resume(): self
    {
        return (new self($this->billable->request('PUT', sprintf('v1/subscriptions/%s/resume', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Delete a given subscription from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function delete(): bool
    {
        return $this->billable->request('DELETE', sprintf('v1/subscriptions/%s', $this->id))->success();
    }
}