<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Throwable;

/**
 * @property string $id
 */
class Invoice extends BaseResource
{
    use HasBillable;

    /**
     * Get temporary download url from given invoice.
     *
     * @throws Throwable
     */
    public function getInvoiceTemporaryUrl(): string
    {
        return $this->billable->request('PUT', sprintf('v1/invoices/%s', $this->id))->data->temporary_url;
    }
}