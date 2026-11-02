<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Contracts\Billable;
use Anikeen\Id\Exceptions\ResourceException;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Checkout sessions of the signed-in user, with the user's access token (any app): several
 * subscriptions the user confirms together on Anikeen ID. Nothing is requested until a method is
 * called.
 */
class CheckoutSessions
{
    use HasBillable;

    public function __construct(Billable|Model $billable)
    {
        $this->billable = $billable;
    }

    /**
     * Open a checkout session with new subscriptions and/or existing drafts of the app
     * (`['id' => …]`); see AppCheckoutSessions::create() for the attributes (no user_id here,
     * vat_rate only for trusted apps).
     *
     * @throws Throwable
     */
    public function create(array $attributes): CheckoutSession
    {
        return (new CheckoutSession(fn () => $this->billable->anikeenId()
            ->request('POST', 'v1/checkout-sessions', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * A session the app created for the user; null when it doesn't exist.
     *
     * @throws Throwable
     */
    public function find(string $id): ?CheckoutSession
    {
        try {
            return (new CheckoutSession(fn () => $this->billable->anikeenId()
                ->request('GET', sprintf('v1/checkout-sessions/%s', $id))))
                ->setBillable($this->billable);
        } catch (ResourceException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }
}
