<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;

class Countries extends BaseCollection
{
    use HasBillable;

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?BaseResource
    {
        return null;
    }
}