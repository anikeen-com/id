<?php

namespace Anikeen\Id\Concerns;

use Throwable;

trait ManagesTaxation
{
    use HasBillable;

    /**
     * Get VAT for the current user.
     *
     * @throws Throwable
     */
    public function vatRate(): float
    {
        return $this->getUserData()->vat_rate;
    }
}