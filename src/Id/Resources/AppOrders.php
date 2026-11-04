<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Contracts\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Orders that bill the app's subscriptions, read with the app's own token (trusted apps,
 * scope orders:client) - of one user ($user->appOrders()) or of all users
 * (app(AnikeenId::class)->appOrders()). Nothing is requested until a method is called.
 *
 * Each order is an object with id, user_id, group, metadata, status
 * (processing|pending|completed|cancelled), is_collective, net_total, vat_total, gross_total,
 * currency_iso, created_at, paid_at, payment_attempts, next_payment_attempt_at,
 * last_payment_error ({code, message} or null), and
 *
 * - items: all lines of the order, each {id, name, description, type, image_url, units, unit,
 *   price, vat_rate, subscription_id, meter, monthly_cap, discount, period_start, period_end,
 *   net_amount, vat_amount, gross_amount}. period_end is exclusive (the start of the next
 *   period); both are null for lines without a period. discount ({label, percent_off,
 *   amount_off}) marks the negative line of a subscription's discount, else null. The net amount
 *   a subscription was billed is the sum of net_amount of its lines.
 * - invoice: null until the order is paid, then {id, number, type, status (pending while the PDF
 *   is being rendered, then completed), service_period_from, service_period_to, created_at,
 *   pdf_url}; see invoicePdf().
 */
class AppOrders
{
    public function __construct(protected Billable|Model|null $billable = null)
    {
    }

    /**
     * All matching orders, oldest first.
     *
     * @param array{status?: string, group?: string} $filters
     * @return list<object>
     *
     * @throws ApiException
     */
    public function all(array $filters = []): array
    {
        $orders = [];
        $page = 1;

        do {
            $result = AppRequest::send('GET', 'v1/client/orders', null, [
                ...$filters,
                ...($this->billable ? ['user_id' => $this->userId()] : []),
                'per_page' => 100,
                'page' => $page,
            ]);

            array_push($orders, ...$result->data);
            $lastPage = (int) ($result->meta->last_page ?? $page);
        } while ($page++ < $lastPage);

        return $orders;
    }

    /**
     * Closed orders that aren't paid (yet), oldest first.
     *
     * @return list<object>
     *
     * @throws ApiException
     */
    public function pending(array $filters = []): array
    {
        return $this->all([...$filters, 'status' => 'pending']);
    }

    /**
     * @throws ApiException
     */
    public function find(string $id): ?object
    {
        try {
            $order = AppRequest::send('GET', sprintf('v1/client/orders/%s', $id))->data;
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }

        return !$this->billable || $order->user_id === $this->userId() ? $order : null;
    }

    /**
     * The PDF of an order's invoice (binary string, with the embedded e-invoice), the same as its
     * `invoice.pdf_url` with the app's token.
     *
     * @throws ApiException 404 "order_not_found", 404 "invoice_not_found" (not paid yet),
     *                      409 "invoice_not_ready" (still being rendered, try again later)
     */
    public function invoicePdf(string $orderId): string
    {
        if ($this->billable && !$this->find($orderId)) {
            throw new ApiException('Order not found.', 404, 'order_not_found');
        }

        return (string) AppRequest::send('GET', sprintf('v1/client/orders/%s/invoice', $orderId))->response()?->getBody();
    }

    private function userId(): string
    {
        return (new AppSubscriptions($this->billable))->userId();
    }
}
