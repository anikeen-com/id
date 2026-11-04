<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasSubscriptionStatus;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use DateTimeInterface;
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
 * @property ?float $monthly_cap net, at most this per calendar month (hour and day units)
 * @property ?float $expected_monthly_price gross estimate for a month (720 hours, at most the monthly cap)
 * @property ?string $predecessor_id the subscription this one replaced (a resize); they share the monthly cap
 * @property ?float $vat_rate
 * @property bool $active
 * @property bool $autorenew
 * @property bool $cancelled
 * @property ?string $status draft, trial, active, paused, cancelling, expired or cancelled, see status()
 * @property ?string $cancels_at when a scheduled cancellation takes effect
 * @property ?string $trial_ends_at
 * @property ?object $discount {label, percent_off, amount_off, duration, duration_in_periods, periods_remaining}; periods_remaining is null for `forever`, 0 once it ended
 * @property ?string $starts_at
 * @property ?string $ends_at
 * @property ?string $accounting_at
 * @property bool $is_past_due
 * @property int $failed_renewal_retries
 * @property ?array $payload
 * @property ?string $idempotency_key
 * @property ?string $checkout_session_id the checkout session the user confirms it in, if any
 * @property ?object $scheduled_change {applies_at, name, description, unit, price, discount, created_at}: a change() for the period end
 */
class AppSubscription implements JsonSerializable
{
    use HasSubscriptionStatus;

    /** change(): prorated, right away. */
    public const APPLY_NOW = 'now';

    /** change(): from the next period on (scheduled_change until then). */
    public const APPLY_PERIOD_END = 'period_end';

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
     * Change a subscription (send only what changes). Before checkout everything can change;
     * afterwards only the description, which names what the customer pays for and is shown
     * on their bills, e.g. `$subscription->update(['description' => 'web-01.example.com'])`.
     * Prices of running subscriptions change with change().
     *
     * @throws ApiException
     */
    public function update(array $attributes): self
    {
        return $this->send('PUT', '', $attributes);
    }

    /**
     * Change a running subscription (send only what changes), like a plan change:
     *
     * - `apply: now`: prorated – the unused rest of the current period is credited at the rate it
     *   was billed at and charged at the new price on the user's open bill. A new unit starts a
     *   new, fully billed period now. A downgrade whose credit exceeds the open bill is refused
     *   (409 "credit_exceeds_bill"): schedule it for the period end instead.
     * - `apply: period_end`: nothing changes now; the subscription has `scheduled_change` and the
     *   next period is billed at the new values. A new scheduled change replaces the previous one.
     *
     * During a free trial nothing was billed, so both just change what the trial ends in.
     * Webhook `subscription.updated` (with `data.previous_attributes`) when the change takes effect.
     *
     * @param array{
     *     apply: string,
     *     price?: float|string,
     *     unit?: string,
     *     name?: string,
     *     description?: null|string,
     *     discount?: null|array{label: string, percent_off?: float|string, amount_off?: float|string, duration: string, duration_in_periods?: int}
     * } $attributes apply: self::APPLY_NOW or self::APPLY_PERIOD_END.
     *               unit: a recurring unit (hour, day, week, month, year).
     *               discount: replaces the discount from the next billed period on, null ends it;
     *               required with a new unit when the current one counts in periods or is an
     *               amount_off. Subscriptions with a monthly cap change only at the period end.
     *
     * @throws ApiException 409 "not_changeable" (not running: draft, paused, cancelling, …),
     *                      "proration_not_possible", "credit_exceeds_bill",
     *                      "billing_address_missing"; 422 validation
     */
    public function change(array $attributes): self
    {
        return $this->send('PUT', '/change', $attributes);
    }

    /**
     * Drop the change scheduled for the period end (nothing happens without one).
     *
     * @throws ApiException
     */
    public function cancelScheduledChange(): self
    {
        return $this->send('DELETE', '/change');
    }

    /**
     * Activate the subscription and bill its first period.
     *
     * $startsAt (in the past) books a service that is already running, e.g. a domain registered
     * earlier: the first period runs from $startsAt, is billed now and renews when it ends.
     *
     * @throws ApiException 409 "already_active", "billing_address_missing", "period_already_ended", …
     */
    public function checkout(DateTimeInterface|string|null $startsAt = null): self
    {
        if ($startsAt instanceof DateTimeInterface) {
            $startsAt = $startsAt->format(DateTimeInterface::ATOM);
        }

        return $this->send('PUT', '/checkout', $startsAt === null ? null : ['starts_at' => $startsAt]);
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
