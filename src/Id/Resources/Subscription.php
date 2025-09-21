<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Contracts\AppTokenRepository;
use Throwable;

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
     *      group: string,
     *      name: string,
     *      description: null|string,
     *      unit: string,
     *      price: float,
     *      vat_rate: float,
     *      payload: null|array,
     *      ends_at: null|string,
     *      webhook_url: null|string,
     *      webhook_secret: null|string
     *  } $attributes The subscription data:
     *    - group:          The group (optional)
     *    - name:           The name (required when set)
     *    - description:    The description (optional)
     *    - unit:           The unit (required when set, e.g. "hour", "day", "week", "month", "year")
     *    - price:          The price per unit (required when set)
     *    - vat_rate:       The VAT rate (required when set)
     *    - payload:        The payload (optional)
     *    - ends_at:        The end date (optional)
     *    - webhook_url:    The webhook URL (optional)
     *    - webhook_secret: The webhook secret (optional)
     * @throws Throwable
     */
    public function update(array $attributes): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/subscriptions/%s', $this->id), $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Force given subscription to check out (trusted apps only).
     *
     * @throws Throwable
     */
    public function checkout(): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/subscriptions/%s/checkout', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Revoke a given running subscription from the current user.
     *
     * @throws Throwable
     */
    public function revoke(bool $refund = false): self
    {
        $attributes = [
            'refund' => $refund,
        ];

        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/subscriptions/%s/revoke', $this->id), $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Pause a given running subscription from the current user.
     *
     * @throws Throwable
     */
    public function pause(): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/subscriptions/%s/pause', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Resume a given running subscription from the current user.
     *
     * @throws Throwable
     */
    public function resume(): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/subscriptions/%s/resume', $this->id))))
            ->setBillable($this->billable);
    }
}
