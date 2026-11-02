<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Concerns\IsCheckoutSession;
use Throwable;

/**
 * A checkout session created with the user's access token, see CheckoutSessions.
 *
 * @property string $id
 * @property string $user_id
 * @property ?string $oauth_client_id
 * @property string $status open, completed, cancelled or expired
 * @property ?string $return_url
 * @property ?object $metadata
 * @property ?string $confirmation_url
 * @property list<string> $subscription_ids
 * @property list<object> $subscriptions
 * @property ?string $expires_at
 * @property ?string $completed_at
 * @property ?string $cancelled_at
 * @property ?string $created_at
 */
class CheckoutSession extends BaseResource
{
    use HasBillable;
    use IsCheckoutSession;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Withdraw an open session: it and its drafts are cancelled.
     *
     * @throws Throwable
     */
    public function cancel(): self
    {
        return (new self(fn () => $this->billable->anikeenId()
            ->request('POST', sprintf('v1/checkout-sessions/%s/cancel', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * @throws Throwable
     */
    public function refresh(): self
    {
        return (new self(fn () => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/checkout-sessions/%s', $this->id))))
            ->setBillable($this->billable);
    }
}
