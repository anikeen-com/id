<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesInvoices
{
    use Request;

    /**
     * Get invoices from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function invoices(): Result
    {
        return $this->request('GET', 'v1/invoices');
    }

    /**
     * Get given invoice from the current user.
     *
     * @param string $invoiceId The invoice ID
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function invoice(string $invoiceId): Result
    {
        return $this->request('GET', sprintf('v1/invoices/%s', $invoiceId));
    }

    /**
     * Get download url from given invoice.
     *
     * @param string $invoiceId The invoice ID
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function getInvoiceDownloadUrl(string $invoiceId): string
    {
        return $this->request('PUT', sprintf('v1/invoices/%s', $invoiceId))->data->download_url;
    }
}