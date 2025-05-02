<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Throwable;

class Subscriptions extends BaseCollection
{
    use HasBillable;

    /**
     * Create a new subscription for the current user.
     *
     * @param array{
     *      group: null|string,
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
     *    - name:           The name
     *    - description:    The description (optional)
     *    - unit:           The unit (e.g. "hour", "day", "week", "month", "year")
     *    - price:          The price per unit
     *    - vat_rate:       The VAT rate (required when set)
     *    - payload:        The payload (optional)
     *    - ends_at:        The end date (optional)
     *    - webhook_url:    The webhook URL (optional)
     *    - webhook_secret: The webhook secret (optional)
     * @throws Throwable
     */
    public function create(array $attributes): Subscription
    {
        return (new Subscription(fn() => $this->billable->request('POST', 'v1/subscriptions', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Subscription
    {
        return (new Subscription(fn() => $this->billable->request('GET', sprintf('v1/subscriptions/%s', $id))))
            ->setBillable($this->billable);
    }
}