<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Contracts\AppTokenRepository;
use Anikeen\Id\Exceptions\ResourceException;
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
     *      monthly_cap?: null|float,
     *      predecessor_id?: null|string,
     *      vat_rate: float,
     *      payload: null|array,
     *      ends_at: null|string,
     *      trial_days?: null|int,
     *      trial_ends_at?: null|string,
     *      trial_once?: bool
     *  } $attributes The subscription data:
     *    - group:          The group (optional)
     *    - name:           The name
     *    - description:    The description (optional)
     *    - unit:           The unit (e.g. "hour", "day", "week", "month", "year")
     *    - price:          The price per unit
     *    - monthly_cap:    Net, at most this per calendar month (hour and day units, max. 2 decimals; optional)
     *    - predecessor_id: The subscription this one replaces (a resize); both share the monthly cap (optional)
     *    - vat_rate:       The VAT rate (required when set)
     *    - payload:        The payload (optional)
     *    - ends_at:        The end date (optional)
     *    - trial_days:     A free trial of 1–365 days from the user's confirmation on (optional)
     *    - trial_ends_at:  Or a fixed end of the free trial (ISO 8601, in the future; optional)
     *    - trial_once:     Refuse the trial if the user already had one with the app in the group (optional)
     * @throws Throwable
     */
    public function create(array $attributes): Subscription
    {
        return (new Subscription(fn() => $this->billable->anikeenId()
            ->request('POST', 'v1/subscriptions', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * {@inheritDoc}
     *
     * @throws ResourceException
     */
    public function find(string $id): ?Subscription
    {
        return (new Subscription(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/subscriptions/%s', $id))))
            ->setBillable($this->billable);
    }
}
