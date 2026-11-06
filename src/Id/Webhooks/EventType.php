<?php

namespace Anikeen\Id\Webhooks;

/**
 * The `type` of webhook events Anikeen ID sends, e.g.
 * `match ($event['type']) { EventType::CHECKOUT_COMPLETED => …, … }`.
 */
final class EventType
{
    public const ORDER_CREATED = 'order.created';
    public const ORDER_COMPLETED = 'order.completed';
    public const ORDER_PAYMENT_FAILED = 'order.payment_failed';
    public const ORDER_CANCELLED = 'order.cancelled';
    public const ORDER_SHIPPED = 'order.shipped';
    /**
     * Money was given back for an order (a refund or a partial cancellation, with a credit note);
     * see the order's `refunds` and `refunded_total`. A full refund also cancels the order,
     * so order.cancelled is sent too; don't rely on the order of the two events.
     */
    public const ORDER_REFUNDED = 'order.refunded';

    public const SUBSCRIPTION_CREATED = 'subscription.created';
    public const SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    public const SUBSCRIPTION_PAUSED = 'subscription.paused';
    public const SUBSCRIPTION_RESUMED = 'subscription.resumed';
    /** `data.reason`: why the billing cancelled it (CancellationReason, e.g. a trial that ended without a payment method), else null. */
    public const SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    public const SUBSCRIPTION_EXPIRED = 'subscription.expired';

    /**
     * The free trial of a running subscription ends in a few days (see trial_ends_at); billing
     * starts then. `data.payment_method_missing`: the user has no payment method yet (with
     * trial_payment_method `optional` the subscription then ends with the trial).
     */
    public const SUBSCRIPTION_TRIAL_WILL_END = 'subscription.trial_will_end';

    /** The last discounted period of a subscription was billed; the following ones are billed at the full price. */
    public const SUBSCRIPTION_DISCOUNT_ENDED = 'subscription.discount_ended';

    /**
     * The price, unit, name, description or discount of a running subscription changed (right away
     * or at the period end); `data.previous_attributes` has the changed fields' old values.
     */
    public const SUBSCRIPTION_UPDATED = 'subscription.updated';

    /**
     * A period of a subscription was booked on its collective order, the first one included;
     * `data.period` describes it, `data.period.first_period` is true for the subscription's first
     * paid period (checkout or the end of a free trial). Opt-in: only endpoints that select this
     * event get it (not through `*`).
     */
    public const SUBSCRIPTION_RENEWED = 'subscription.renewed';

    /** The user confirmed a checkout session: all of its subscriptions are running (sent after their subscription.activated). */
    public const CHECKOUT_COMPLETED = 'checkout.completed';

    /** The user or the app cancelled a checkout session; its drafts were cancelled. */
    public const CHECKOUT_CANCELLED = 'checkout.cancelled';

    /** A checkout session was not confirmed within 24 hours; its drafts were cancelled. */
    public const CHECKOUT_EXPIRED = 'checkout.expired';

    /**
     * A user's open monthly bill reached their billing limit: start no new paid service for them.
     * `data.object` is the billing limit as the app reads it (with `version`), `data.reason`
     * `booking` or `limit_changed`. Opt-in: only endpoints that select this event get it (not
     * through `*`), and only apps that may read the limit of a user connected to them.
     */
    public const BILLING_LIMIT_REACHED = 'billing_limit.reached';

    /**
     * A user whose billing limit was reached has room again. `data.object` is the billing limit
     * (with `version`), `data.reason` `limit_changed`, `limit_removed`, `voided`, `period_reset`
     * or `order_changed`. Opt-in like BILLING_LIMIT_REACHED.
     */
    public const BILLING_LIMIT_AVAILABLE = 'billing_limit.available';

    public const TEST = 'webhook.test';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    private function __construct()
    {
    }
}
