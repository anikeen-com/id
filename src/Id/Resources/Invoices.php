<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class Invoices extends BaseCollection
{
    use HasBillable;

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Invoice
    {
        $result = $this->billable->request('GET', sprintf('v1/invoices/%s', $id));

        return $result->success()
            ? (new Invoice($result))
                ->setBillable($this->billable)
            : null;
    }
}