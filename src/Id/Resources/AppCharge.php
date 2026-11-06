<?php

namespace Anikeen\Id\Resources;

use DateTimeImmutable;
use JsonSerializable;

/**
 * A charge a trusted app booked onto a user's open monthly bill, see
 * AnikeenId::createChargeForUser(). Amounts are decimal strings (`unit_price` with six decimals,
 * the others with two).
 *
 * @property string $object "charge"
 * @property string $id
 * @property string $user_id
 * @property string $status open (its bill is open), billed (the bill was closed) or voided
 * @property string $idempotency_key
 * @property string $name the line on the bill
 * @property ?string $description
 * @property string $type digital, physical or physical-reduced (the VAT treatment)
 * @property int $quantity
 * @property string $unit "piece"
 * @property string $unit_price net per piece
 * @property string $net_amount
 * @property string $gross_amount what the charge added to the bill, VAT included
 * @property string $currency_iso
 * @property ?string $group
 * @property string $occurred_at the line's service date (ISO 8601)
 * @property ?string $reference the app's own id
 * @property object $metadata the app's own data (string values)
 * @property ?string $order_id the monthly bill (order) the line is on; follows the line when a rollover moves it
 * @property ?string $voided_at
 * @property ?string $void_reason
 * @property string $created_at
 */
class AppCharge implements JsonSerializable
{
    public const STATUS_OPEN = 'open';

    public const STATUS_BILLED = 'billed';

    public const STATUS_VOIDED = 'voided';

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

    public function id(): string
    {
        return $this->data->id;
    }

    /**
     * open, billed or voided (the STATUS_* constants).
     */
    public function status(): string
    {
        return $this->data->status;
    }

    /**
     * Its bill is still open: the charge can be voided.
     */
    public function isOpen(): bool
    {
        return $this->status() === self::STATUS_OPEN;
    }

    /**
     * Its bill was closed (paid, waiting for payment or cancelled): only a refund of the order by
     * Anikeen credits it.
     */
    public function isBilled(): bool
    {
        return $this->status() === self::STATUS_BILLED;
    }

    public function isVoided(): bool
    {
        return $this->status() === self::STATUS_VOIDED;
    }

    /**
     * Net, e.g. "8.00".
     */
    public function netAmount(): string
    {
        return $this->data->net_amount;
    }

    /**
     * Gross, what the charge added to the bill, e.g. "9.52".
     */
    public function grossAmount(): string
    {
        return $this->data->gross_amount;
    }

    /**
     * The monthly bill (order) the charge is on, null once its line is gone (voided).
     */
    public function orderId(): ?string
    {
        return $this->data->order_id ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return (array) ($this->data->metadata ?? []);
    }

    public function occurredAt(): ?DateTimeImmutable
    {
        return isset($this->data->occurred_at) ? new DateTimeImmutable($this->data->occurred_at) : null;
    }

    public function toArray(): array
    {
        return (array) $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
