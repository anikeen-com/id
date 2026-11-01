<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Concerns\HasParent;
use Throwable;

/**
 * Deliveries of an order's physical goods. Each one adds a delivery note to the customer's
 * order documents on Anikeen ID.
 */
class Shipments extends BaseCollection
{
    use HasBillable;
    use HasParent;

    /**
     * Ship goods of a paid order. Only the app that created the order (or a trusted app) can.
     *
     * Without `items`, everything physical that hasn't been shipped yet is shipped. Items and
     * the shipping address are stored as they are now; the user sees them on the delivery note.
     *
     * @param array{
     *     carrier?: string,
     *     tracking_number?: string,
     *     tracking_url?: string,
     *     note?: string,
     *     shipped_at?: string,
     *     items?: array<array{order_item_id: string, units?: float|int|string}>
     * } $attributes
     *   - carrier:         e.g. "DHL"
     *   - tracking_url:    http(s) link to track the parcel, shown to the user
     *   - shipped_at:      when it was sent (default: now, not in the future)
     *   - items:           physical items of the order and how many of them (default: all left)
     *
     * Errors (ResourceException with the HTTP status): 409 when the order isn't paid or nothing
     * is left to ship, 422 for items that aren't physical items of the order or exceed what's left.
     *
     * @throws Throwable
     */
    public function create(array $attributes = []): Shipment
    {
        return (new Shipment(fn() => $this->billable->anikeenId()
            ->request('POST', sprintf('v1/orders/%s/shipments', $this->parent->id), $attributes)))
            ->setBillable($this->billable)
            ->setParent($this->parent);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Shipment
    {
        return (new Shipment(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/orders/%s/shipments/%s', $this->parent->id, $id))))
            ->setBillable($this->billable)
            ->setParent($this->parent);
    }
}
