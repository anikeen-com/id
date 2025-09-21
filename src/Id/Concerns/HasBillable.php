<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Contracts\Billable;
use Illuminate\Database\Eloquent\Model;

trait HasBillable
{
    public Billable|Model $billable;

    public function setBillable(Billable|Model $billable): self
    {
        $this->billable = $billable;

        return $this;
    }

    public function getBillable(): Billable
    {
        return $this->billable;
    }
}