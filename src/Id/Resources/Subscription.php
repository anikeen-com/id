<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @property string $id
 */
class Subscription extends BaseResource
{
    use HasBillable;

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