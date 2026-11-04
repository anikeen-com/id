<?php

namespace Anikeen\Id\Enums;

/**
 * The state of a subscription, see Subscription::status() and AppSubscription::status().
 */
class SubscriptionStatus
{
    /** Created, not checked out or confirmed by the user yet. */
    const DRAFT = 'draft';

    /** Running and billed, in its trial period. */
    const TRIAL = 'trial';

    /** Running and billed. */
    const ACTIVE = 'active';

    /** Paused: runs until its paid period ends, then expires. */
    const PAUSED = 'paused';

    /** Running until a scheduled cancellation takes effect. */
    const CANCELLING = 'cancelling';

    /** Paused and ran out: can be resumed (new period) or cancelled. */
    const EXPIRED = 'expired';

    /** Cancelled for good. */
    const CANCELLED = 'cancelled';

    /** None of the above. */
    const INACTIVE = 'inactive';
}
