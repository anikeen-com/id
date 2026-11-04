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

    public const SUBSCRIPTION_CREATED = 'subscription.created';
    public const SUBSCRIPTION_ACTIVATED = 'subscription.activated';
    public const SUBSCRIPTION_PAUSED = 'subscription.paused';
    public const SUBSCRIPTION_RESUMED = 'subscription.resumed';
    public const SUBSCRIPTION_CANCELLED = 'subscription.cancelled';
    public const SUBSCRIPTION_EXPIRED = 'subscription.expired';

    /** The free trial of a running subscription ends in a few days (see trial_ends_at); billing starts then. */
    public const SUBSCRIPTION_TRIAL_WILL_END = 'subscription.trial_will_end';

    /** The last discounted period of a subscription was billed; the following ones are billed at the full price. */
    public const SUBSCRIPTION_DISCOUNT_ENDED = 'subscription.discount_ended';

    /** The user confirmed a checkout session: all of its subscriptions are running (sent after their subscription.activated). */
    public const CHECKOUT_COMPLETED = 'checkout.completed';

    /** The user or the app cancelled a checkout session; its drafts were cancelled. */
    public const CHECKOUT_CANCELLED = 'checkout.cancelled';

    /** A checkout session was not confirmed within 24 hours; its drafts were cancelled. */
    public const CHECKOUT_EXPIRED = 'checkout.expired';

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
