<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class Subscriptions extends BaseCollection
{
    use HasBillable;

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
    public function create(array $attributes): Subscription
    {
        return (new Subscription($this->billable->request('POST', 'v1/subscriptions', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Subscription
    {
        $result = $this->billable->request('GET', sprintf('v1/subscriptions/%s', $id));

        return $result->success()
            ? (new Subscription($result))
                ->setBillable($this->billable)
            : null;
    }
}