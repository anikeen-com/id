<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Contracts\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Checkout sessions created with the app's own token (trusted apps, scope `subscriptions:client`):
 * several subscriptions of one user that the user confirms together on Anikeen ID, instead of the
 * app checking them out itself. For one user ($user->appCheckoutSessions()) or, to look sessions
 * up e.g. from a webhook, for all users (app(AnikeenId::class)->appCheckoutSessions()).
 * Nothing is requested until a method is called.
 */
class AppCheckoutSessions
{
    public function __construct(protected Billable|Model|null $billable = null)
    {
    }

    /**
     * Open a checkout session with new subscriptions (their attributes, like
     * AppSubscriptions::create()) and/or existing drafts of the app (`['id' => …]`).
     *
     * @param array{
     *     subscriptions: list<array{id: string}|array{
     *         group?: null|string,
     *         name: string,
     *         description?: null|string,
     *         unit: string,
     *         price: float|string,
     *         monthly_cap?: null|float|string,
     *         predecessor_id?: null|string,
     *         vat_rate?: float,
     *         payload?: null|array,
     *         ends_at?: null|string
     *     }>,
     *     return_url?: null|string,
     *     metadata?: null|array,
     *     idempotency_key?: null|string,
     *     user_id?: string
     * } $attributes return_url must share scheme, host and port with one of the app's redirect
     *               URIs; the user comes back to it with `status` and `checkout_session`. A
     *               repeated idempotency_key returns the first session instead of a new one.
     *
     * @throws ApiException 422 validation (e.g. return_url), 409 "idempotency_key_conflict"
     */
    public function create(array $attributes): AppCheckoutSession
    {
        if ($this->billable) {
            $attributes['user_id'] = (new AppSubscriptions($this->billable))->userId();
        } elseif (empty($attributes['user_id'])) {
            throw new InvalidArgumentException('A checkout session needs a user: use $user->appCheckoutSessions() or pass user_id.');
        }

        return new AppCheckoutSession(AppRequest::send('POST', 'v1/client/checkout-sessions', $attributes)->data);
    }

    /**
     * A session of the app; null when it doesn't exist (or belongs to another user).
     *
     * @throws ApiException
     */
    public function find(string $id): ?AppCheckoutSession
    {
        try {
            $session = new AppCheckoutSession(AppRequest::send('GET', sprintf('v1/client/checkout-sessions/%s', $id))->data);
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }

        return !$this->billable || $session->user_id === (new AppSubscriptions($this->billable))->userId() ? $session : null;
    }
}
