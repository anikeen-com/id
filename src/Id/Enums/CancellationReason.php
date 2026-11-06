<?php

namespace Anikeen\Id\Enums;

/**
 * `cancellation_reason` of a subscription (and `data.reason` of `subscription.cancelled`): why
 * Anikeen ID's billing ended it. Null when the user, the app or an admin cancelled it.
 */
class CancellationReason
{
    /** Its free trial (`trial_payment_method` optional) ended without a payment method: cancelled at the trial's end, nothing billed. */
    const TRIAL_ENDED_WITHOUT_PAYMENT_METHOD = 'trial_ended_without_payment_method';
}
