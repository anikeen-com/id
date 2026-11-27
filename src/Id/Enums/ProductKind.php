<?php

namespace Anikeen\Id\Enums;

/**
 * `kind` of a product in the app's catalog (AppInventory).
 */
class ProductKind
{
    /**
     * Goods (the default): sold with orders, with stock, shipping and product safety; unit piece or
     * onetime. Books can be price-bound (Buchpreisbindung).
     */
    const GOODS = 'goods';

    /**
     * A service, e.g. a server, a domain or storage: sold with subscriptions (`product_id`, Anikeen
     * ID computes the price) or as metered usage. Units hour, day, week, month, year, onetime,
     * gigabyte or gibibyte; never stock, never price-bound.
     */
    const SERVICE = 'service';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::GOODS, self::SERVICE];
    }
}
