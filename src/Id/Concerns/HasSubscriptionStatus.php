<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Enums\SubscriptionStatus;

/**
 * The state of a subscription, derived from its fields the same way Anikeen ID derives `status`
 * (so it also works with responses that only carry the fields).
 */
trait HasSubscriptionStatus
{
    /**
     * One of the SubscriptionStatus constants: draft, trial, active, paused, cancelling, expired
     * or cancelled.
     */
    public function status(): string
    {
        $active = (bool) $this->active;
        $started = !empty($this->starts_at);

        return match (true) {
            (bool) $this->cancelled => SubscriptionStatus::CANCELLED,
            !$active && !$started => SubscriptionStatus::DRAFT,
            $active && !empty($this->cancels_at) => SubscriptionStatus::CANCELLING,
            $active && !$this->autorenew => SubscriptionStatus::PAUSED,
            $active && !empty($this->trial_ends_at) && strtotime($this->trial_ends_at) > time() => SubscriptionStatus::TRIAL,
            $active => SubscriptionStatus::ACTIVE,
            !$this->autorenew && $started => SubscriptionStatus::EXPIRED,
            default => SubscriptionStatus::INACTIVE,
        };
    }

    /**
     * Not checked out or confirmed yet (third-party apps: send the user to confirmationUrl()).
     */
    public function isDraft(): bool
    {
        return $this->status() === SubscriptionStatus::DRAFT;
    }

    /**
     * Running and paid: active, in trial, paused or with a scheduled cancellation. Grant access
     * while this is true.
     */
    public function isActive(): bool
    {
        return in_array($this->status(), [SubscriptionStatus::TRIAL, SubscriptionStatus::ACTIVE, SubscriptionStatus::PAUSED, SubscriptionStatus::CANCELLING], true);
    }

    /**
     * Paused: runs until the paid period ends, then expires (resume() to continue).
     */
    public function isPaused(): bool
    {
        return $this->status() === SubscriptionStatus::PAUSED;
    }

    /**
     * Running until a scheduled cancellation takes effect.
     */
    public function isCancelling(): bool
    {
        return $this->status() === SubscriptionStatus::CANCELLING;
    }

    /**
     * Paused and ran out: resume() starts a new period.
     */
    public function isExpired(): bool
    {
        return $this->status() === SubscriptionStatus::EXPIRED;
    }

    /**
     * Cancelled for good.
     */
    public function isCancelled(): bool
    {
        return $this->status() === SubscriptionStatus::CANCELLED;
    }
}
