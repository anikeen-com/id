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
 * Each order is an object with id, user_id, group, status (processing|pending|completed|cancelled),
 * is_collective, net_total, vat_total, gross_total, currency_iso, created_at, paid_at,
 * payment_attempts, next_payment_attempt_at and last_payment_error ({code, message} or null).
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

    private function userId(): string
    {
        return (new AppSubscriptions($this->billable))->userId();
    }
}
