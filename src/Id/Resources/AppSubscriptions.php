<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Contracts\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Subscriptions of a user, managed with the app's own token (client credentials, scope
 * `subscriptions:client`) instead of the user's token, so they keep working when the user's
 * login expired. The app has to be trusted by the ID service. Nothing is requested until a
 * method is called.
 */
class AppSubscriptions
{
    public function __construct(protected Billable|Model $billable)
    {
    }

    /**
     * The user's Anikeen ID (see AnikeenId::useUserIdField()).
     */
    public function userId(): string
    {
        $userId = $this->billable->{AnikeenId::getUserIdField()} ?? null;

        if (empty($userId)) {
            throw new InvalidArgumentException(sprintf('The billable has no Anikeen ID in [%s].', AnikeenId::getUserIdField()));
        }

        return (string) $userId;
    }

    /**
     * Create a subscription for the user (it is billed only after checkout()).
     *
     * @param array{
     *     group?: null|string,
     *     name: string,
     *     description?: null|string,
     *     unit: string,
     *     price: float|string,
     *     vat_rate?: float,
     *     payload?: null|array,
     *     ends_at?: null|string,
     *     webhook_url?: null|string,
     *     webhook_secret?: null|string,
     *     idempotency_key?: null|string
     * } $attributes With an idempotency_key (max. 191 characters), a retried call returns the
     *               subscription created by the first one instead of a duplicate.
     *
     * @throws ApiException
     */
    public function create(array $attributes): AppSubscription
    {
        return new AppSubscription(AppRequest::send('POST', 'v1/client/subscriptions', [
            ...$attributes,
            'user_id' => $this->userId(),
        ])->data);
    }

    /**
     * Find one of the user's subscriptions; null when it doesn't exist (or belongs to someone else).
     *
     * @throws ApiException
     */
    public function find(string $id): ?AppSubscription
    {
        try {
            $subscription = new AppSubscription(AppRequest::send('GET', sprintf('v1/client/subscriptions/%s', $id))->data);
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }

        return $subscription->user_id === $this->userId() ? $subscription : null;
    }

    /**
     * All of the user's subscriptions, optionally filtered.
     *
     * @param array{group?: string, active?: bool, cancelled?: bool} $filters
     * @return list<AppSubscription>
     *
     * @throws ApiException
     */
    public function all(array $filters = []): array
    {
        $subscriptions = [];
        $page = 1;

        do {
            $result = AppRequest::send('GET', 'v1/client/subscriptions', null, [
                ...array_map(fn ($value) => is_bool($value) ? (int) $value : $value, $filters),
                'user_id' => $this->userId(),
                'per_page' => 100,
                'page' => $page,
            ]);

            foreach ($result->data as $data) {
                $subscriptions[] = new AppSubscription($data);
            }

            $lastPage = (int) ($result->meta->last_page ?? $page);
        } while ($page++ < $lastPage);

        return $subscriptions;
    }
}
