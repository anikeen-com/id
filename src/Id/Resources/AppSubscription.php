<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use JsonSerializable;

/**
 * A subscription managed by the app itself (client credentials), see AppSubscriptions.
 *
 * @property string $id
 * @property string $user_id
 * @property ?string $group
 * @property string $name
 * @property ?string $description
 * @property string $unit
 * @property float $price
 * @property ?float $vat_rate
 * @property bool $active
 * @property bool $autorenew
 * @property bool $cancelled
 * @property ?string $status "active" or "inactive"
 * @property ?string $starts_at
 * @property ?string $ends_at
 * @property ?string $accounting_at
 * @property bool $is_past_due
 * @property int $failed_renewal_retries
 * @property ?array $payload
 * @property ?string $idempotency_key
 */
class AppSubscription implements JsonSerializable
{
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
     * Change a subscription that wasn't checked out yet.
     *
     * @throws ApiException
     */
    public function update(array $attributes): self
    {
        return $this->send('PUT', '', $attributes);
    }

    /**
     * Activate the subscription and bill its first period.
     *
     * @throws ApiException 409 "already_active", "billing_address_missing", …
     */
    public function checkout(): self
    {
        return $this->send('PUT', '/checkout');
    }

    /**
     * Cancel the subscription. With $refund (only within 14 days of its start), its not yet
     * charged usage is credited.
     *
     * @throws ApiException 409 "already_cancelled", 404 "subscription_not_found", …
     */
    public function revoke(bool $refund = false): self
    {
        return $this->send('PUT', '/revoke', ['refund' => $refund]);
    }

    /**
     * Stop renewing; the subscription expires when its paid period ends.
     *
     * @throws ApiException
     */
    public function pause(): self
    {
        return $this->send('PUT', '/pause');
    }

    /**
     * Renew again (an expired subscription starts a new period).
     *
     * @throws ApiException
     */
    public function resume(): self
    {
        return $this->send('PUT', '/resume');
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
    private function send(string $method, string $action, ?array $payload = null): self
    {
        $this->data = AppRequest::send($method, sprintf('v1/client/subscriptions/%s%s', $this->id, $action), $payload)->data;

        return $this;
    }
}
