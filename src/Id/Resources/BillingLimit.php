<?php

namespace Anikeen\Id\Resources;

use DateTimeImmutable;
use JsonSerializable;

/**
 * A user's monthly billing limit: one gross limit for their whole open monthly bill across all
 * Anikeen services, see AnikeenId::billingLimitForUser() and the `billing_limit` key of
 * billingOverview(). Amounts are decimal strings with two decimals.
 *
 * Without a limit `limit`, `remaining` and `remaining_net` are null; for an app `used` is null
 * too (an app sees the user's whole open bill only while they have set a limit). `used_by_app`
 * (the open bills of your app) is there only when an app reads the limit.
 *
 * @property string $object "billing_limit"
 * @property string $user_id
 * @property string $currency "EUR"
 * @property string $basis "gross"
 * @property ?string $limit
 * @property ?string $used the user's open monthly bills across all Anikeen services
 * @property ?string $used_by_app the open monthly bills of your app (app reads only)
 * @property ?string $remaining max(0, limit − used)
 * @property ?string $remaining_net remaining at the user's VAT rate, rounded down (a hint)
 * @property int|float|null $vat_rate null without a billing address
 * @property bool $reached used >= limit: start no new paid service
 * @property string $resets_at when this month's bills are closed (ISO 8601)
 * @property int $version only goes up; keep the highest seen (also in the billing_limit.* webhooks)
 */
class BillingLimit implements JsonSerializable
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
     * The user has set a limit (`0.00` is one too: no new charges at all).
     */
    public function hasLimit(): bool
    {
        return $this->limit() !== null;
    }

    /**
     * Gross, null without a limit.
     */
    public function limit(): ?string
    {
        return $this->data->limit ?? null;
    }

    /**
     * The user's open monthly bills across all Anikeen services (gross). Null for an app while the
     * user has no limit.
     */
    public function used(): ?string
    {
        return $this->data->used ?? null;
    }

    /**
     * The open monthly bills of your app (gross); null outside an app read (billingOverview()).
     * `used − used_by_app` is what the user spends on their other Anikeen services.
     */
    public function usedByApp(): ?string
    {
        return $this->data->used_by_app ?? null;
    }

    /**
     * Gross room left (never negative), null without a limit.
     */
    public function remaining(): ?string
    {
        return $this->data->remaining ?? null;
    }

    /**
     * The room left as a net amount at the user's VAT rate, rounded down: a hint only (the VAT of
     * a charge depends on its type). Null without a limit or a billing address.
     */
    public function remainingNet(): ?string
    {
        return $this->data->remaining_net ?? null;
    }

    /**
     * The user's VAT rate in percent (e.g. 19.0), null without a billing address.
     */
    public function vatRate(): ?float
    {
        return isset($this->data->vat_rate) ? (float) $this->data->vat_rate : null;
    }

    public function currency(): string
    {
        return $this->data->currency ?? 'EUR';
    }

    /**
     * The limit is reached (used >= limit): start no new paid service for the user.
     */
    public function isReached(): bool
    {
        return (bool) ($this->data->reached ?? false);
    }

    /**
     * When this month's bills are closed and `used` drops.
     */
    public function resetsAt(): ?DateTimeImmutable
    {
        return isset($this->data->resets_at) ? new DateTimeImmutable($this->data->resets_at) : null;
    }

    /**
     * Only goes up: keep the highest one seen and ignore webhooks with a lower or equal one.
     */
    public function version(): int
    {
        return (int) ($this->data->version ?? 0);
    }

    /**
     * Whether a charge of $gross (gross, e.g. "4.76") would fit as of this read: always without a
     * limit, else at most `remaining`. Advisory only: it reserves nothing, the binding check is
     * the booking (409 "billing_limit_reached").
     */
    public function allows(string|int|float $gross): bool
    {
        if (!$this->hasLimit()) {
            return true;
        }

        $remaining = $this->remaining();

        return $remaining !== null && self::compare($gross, $remaining) <= 0;
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
     * Compares two non-negative decimal amounts exactly (as digit strings, no float rounding);
     * negative amounts count as zero.
     */
    private static function compare(string|int|float $a, string|int|float $b): int
    {
        return strcmp(self::micros($a), self::micros($b)) <=> 0;
    }

    /**
     * The amount in millionths as a zero-padded digit string of a fixed width.
     */
    private static function micros(string|int|float $amount): string
    {
        $amount = is_float($amount) ? number_format($amount, 6, '.', '') : trim((string) $amount);

        if (str_starts_with($amount, '-')) {
            return str_repeat('0', 40);
        }

        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '+'), 2), 2, '');
        $digits = preg_replace('/\D/', '', $whole) . substr(str_pad(preg_replace('/\D/', '', $fraction), 6, '0'), 0, 6);

        return str_pad(ltrim($digits, '0'), 40, '0', STR_PAD_LEFT);
    }
}
