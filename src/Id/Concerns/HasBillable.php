<?php

namespace Anikeen\Id\Concerns;

trait HasBillable
{
    public mixed $billable;

    public function setBillable(mixed $billable): self
    {
        $this->billable = $billable;

        return $this;
    }

    public function getBillable(): mixed
    {
        return $this->billable;
    }
}