<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Contracts\Billable;
use Illuminate\Database\Eloquent\Model;

trait HasBillable
{
    protected Billable|Model $billable;

    public function setBillable(Billable|Model $billable): self
    {
        $this->billable = $billable;

        return $this;
    }

    public function getBillable(): Billable|Model
    {
        return $this->billable;
    }
}