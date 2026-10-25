<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;

/**
 * @property string $id
 */
class PaymentMethod extends BaseResource
{
    use HasBillable;

    /**
     * Remove this payment method from the current user.
     *
     * @throws \Throwable
     */
    public function delete(): bool
    {
        return $this->billable->anikeenId()
            ->request('DELETE', sprintf('v1/payment-methods/%s', $this->id))
            ->success();
    }
}