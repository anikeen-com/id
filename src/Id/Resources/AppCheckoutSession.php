<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\IsCheckoutSession;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use JsonSerializable;

/**
 * A checkout session created with the app's own token, see AppCheckoutSessions.
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
class AppCheckoutSession implements JsonSerializable
{
    use IsCheckoutSession;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public function __construct(protected object $data)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->data->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data->{$name});
    }

    /**
     * The session's subscriptions (drafts until the user confirms).
     *
     * @return list<AppSubscription>
     */
    public function subscriptions(): array
    {
        return array_map(fn (object $subscription) => new AppSubscription($subscription), array_values((array) ($this->data->subscriptions ?? [])));
    }

    /**
     * Withdraw an open session (e.g. the cart changed): it and its drafts are cancelled.
     *
     * @throws ApiException 409 "checkout_session_completed", "checkout_session_cancelled", "checkout_session_expired"
     */
    public function cancel(): self
    {
        return $this->send('POST', '/cancel');
    }

    /**
     * @throws ApiException
     */
    public function refresh(): self
    {
        return $this->send('GET', '');
    }

    public function toArray(): array
    {
        return (array) $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @throws ApiException
     */
    private function send(string $method, string $action): self
    {
        $this->data = AppRequest::send($method, sprintf('v1/client/checkout-sessions/%s%s', $this->id, $action))->data;

        return $this;
    }
}
