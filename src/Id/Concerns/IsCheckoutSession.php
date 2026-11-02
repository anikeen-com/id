<?php

namespace Anikeen\Id\Concerns;

/**
 * Reading a checkout session (see AppCheckoutSession and CheckoutSession).
 */
trait IsCheckoutSession
{
    /**
     * Where the user confirms (or cancels) all of the session's subscriptions, or null once the
     * session is no longer open. The user then returns to the session's `return_url` with
     * `status` (completed or cancelled when confirming or cancelling; expired only from the
     * "back to app" link of a session that had expired) and `checkout_session` (the id). Check the
     * status through the API or the checkout.* webhooks; don't trust the query string.
     */
    public function confirmationUrl(): ?string
    {
        return $this->confirmation_url ?: null;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Confirmed: all of its subscriptions are running.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Cancelled or expired: all of its drafts were cancelled.
     */
    public function isCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_EXPIRED], true);
    }

    /**
     * @return list<string>
     */
    public function subscriptionIds(): array
    {
        return array_values((array) ($this->subscription_ids ?? []));
    }
}
