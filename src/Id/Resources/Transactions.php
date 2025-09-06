<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\ResourceException;
use Throwable;

class Transactions extends BaseCollection
{
    use HasBillable;

    /**
     * Create a new transaction for the current user.
     *
     * @param array{
     *       group: null|string,
     *       invoice_id: null|string,
     *       payment_provider: null|string,
     *       payment_intent: null|string,
     *       status: string,
     *       type: string,
     *       amount: float,
     *       created_at: string
     *   } $attributes The attributes for the transaction.
     *     - group:            The group (optional)
     *     - invoice_id:       The invoice id (optional)
     *     - payment_provider: The payment provider (optional, e.g. "kofi", "stripe")
     *     - payment_intent:   The payment intent (optional)
     *     - status:           The status (e.g. "expired", "failed", "pending", "refunded", "succeeded")
     *     - type:             The type (e.g. "deposit", "withdrawal")
     *     - amount:           The amount
     *     - created_at:       The created at datetime string (e.g. "Y-M-D H:i:s")
     * @throws Throwable
     */
    public function create(array $attributes = []): Transaction
    {
        return (new Transaction($this->billable->request('POST', 'v1/transactions', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Transaction
    {
        return (new Transaction(fn() => $this->billable->request('GET', sprintf('v1/transactions/%s', $id))))
            ->setBillable($this->billable);
    }
}