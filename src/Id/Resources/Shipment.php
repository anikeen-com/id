<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Concerns\HasParent;
use Throwable;

/**
 * @property string $id
 * @property string $number delivery note number, e.g. LS-2026-0001
 * @property string $order_id
 * @property null|string $carrier
 * @property null|string $tracking_number
 * @property null|string $tracking_url
 * @property array $items order_item_id, name, description, units
 * @property object $shipping_address
 * @property null|string $note
 * @property string $shipped_at
 */
class Shipment extends BaseResource
{
    use HasBillable;
    use HasParent;

    /**
     * The delivery note as PDF (binary string).
     *
     * @throws Throwable
     */
    public function deliveryNote(): string
    {
        $result = $this->billable->anikeenId()
            ->request('GET', sprintf('v1/orders/%s/shipments/%s/delivery-note', $this->order_id, $this->id));

        return (string) $result->response?->getBody();
    }
}
