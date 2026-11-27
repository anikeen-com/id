<?php

namespace Anikeen\Id\Enums;

/**
 * `first_period` of a new catalog subscription (AppSubscriptions::create(), checkout sessions,
 * AppInventory::price()/quotes()): what its first period is when the service has a setup or
 * transfer price, e.g. a domain's registration or transfer. A dearer first period is billed once;
 * Anikeen ID changes the subscription to the renewal price at its end by itself.
 */
class FirstPeriod
{
    /** A new setup (the default): the list price plus the product's `setup_price`. */
    const SETUP = 'setup';

    /** Moved in (e.g. a domain transfer): at least the product's `transfer_price`. */
    const TRANSFER = 'transfer';

    /** A period like every other one, e.g. a service that already runs (takeovers always). */
    const NONE = 'none';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SETUP, self::TRANSFER, self::NONE];
    }
}
