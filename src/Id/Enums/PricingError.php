<?php

namespace Anikeen\Id\Enums;

/**
 * Why Anikeen ID refused to sell a catalog service (422 with `error`, see
 * ApiException::isPricingError()): from AppInventory::price(), subscriptions, checkout sessions and
 * change() with `product_id`, and usage records with `product_id`. `errors` names the field
 * (`product_id` or `coupon_code`, in checkout sessions `subscriptions.<index>.product_id` …).
 */
class PricingError
{
    /**
     * The product is not in the app's catalog (subscriptions, checkout sessions; AppInventory::price()
     * answers an unknown product with 404 instead, see ApiException::isNotFound()).
     */
    const PRODUCT_NOT_FOUND = 'product_not_found';

    /** The product is goods: only services are sold with subscriptions (goods with orders). */
    const PRODUCT_NOT_A_SERVICE = 'product_not_a_service';

    /** The product is not available. */
    const PRODUCT_UNAVAILABLE = 'product_unavailable';

    /** A subscription needs a recurring unit (hour, day, week, month, year). */
    const PRODUCT_NOT_RECURRING = 'product_not_recurring';

    /** Usage records need a meter product (a service with unit gigabyte or gibibyte). */
    const PRODUCT_NOT_METERED = 'product_not_metered';

    /** No coupon with this code. */
    const COUPON_INVALID = 'coupon_invalid';

    /** The coupon has expired. */
    const COUPON_EXPIRED = 'coupon_expired';

    /** The coupon has no uses left (drafts of the last 7 days hold theirs). */
    const COUPON_USED_UP = 'coupon_used_up';

    /** The coupon is not for this product, or not a percentage (services only take percentages). */
    const COUPON_NOT_APPLICABLE = 'coupon_not_applicable';

    /** Resellers buy at their reseller price; no coupon on top. */
    const COUPON_NOT_ALLOWED = 'coupon_not_allowed';

    /** The product (`coupon_required`) is only sold with a coupon. */
    const COUPON_REQUIRED = 'coupon_required';

    /**
     * change() to a product with another monthly cap than the subscription: replace the subscription
     * (a new one with `predecessor_id`) instead.
     */
    const MONTHLY_CAP_DIFFERS = 'monthly_cap_differs';

    /**
     * A first period that costs more (setup, transfer; FirstPeriod) can't start with a free trial:
     * send `first_period` none or no trial.
     */
    const FIRST_PERIOD_WITH_TRIAL = 'first_period_with_trial';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PRODUCT_NOT_FOUND,
            self::PRODUCT_NOT_A_SERVICE,
            self::PRODUCT_UNAVAILABLE,
            self::PRODUCT_NOT_RECURRING,
            self::PRODUCT_NOT_METERED,
            self::COUPON_INVALID,
            self::COUPON_EXPIRED,
            self::COUPON_USED_UP,
            self::COUPON_NOT_APPLICABLE,
            self::COUPON_NOT_ALLOWED,
            self::COUPON_REQUIRED,
            self::MONTHLY_CAP_DIFFERS,
            self::FIRST_PERIOD_WITH_TRIAL,
        ];
    }

    /**
     * Whether the code is about the coupon (the rest is about the product).
     */
    public static function isCouponError(?string $code): bool
    {
        return $code !== null && in_array($code, self::all(), true) && str_starts_with($code, 'coupon_');
    }
}
