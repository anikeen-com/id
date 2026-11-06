<?php

namespace Anikeen\Id\Enums;

/**
 * `trial_payment_method` of a subscription with a free trial: whether the user needs a payment
 * method to confirm it. Null on subscriptions without a trial.
 */
class TrialPaymentMethod
{
    /** A payment method is needed to confirm the trial (the default). */
    const REQUIRED = 'required';

    /**
     * The trial can be confirmed without a payment method (if nothing else has to be paid). If the
     * user still has none when the trial ends, the subscription is cancelled then instead of billed
     * (`cancellation_reason` CancellationReason::TRIAL_ENDED_WITHOUT_PAYMENT_METHOD).
     */
    const OPTIONAL = 'optional';
}
