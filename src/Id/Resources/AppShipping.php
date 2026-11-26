<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;

/**
 * The app's shipping zones (like WooCommerce's), with the app's own token (scope
 * inventory:client): app(AnikeenId::class)->shipping(). Nothing is requested until a method is
 * called.
 *
 * Zones are tried in `sort` order; the first whose countries (and, if set, postal code patterns)
 * match the shipping address offers its methods. An order created with `calculate_shipping`
 * gets the chosen method as shipping items; its VAT follows the goods (split by their VAT type).
 * Without a matching zone the customer can't pay until they pick another shipping address.
 *
 * Each zone is an object with id, name, countries (ISO codes), postal_codes (list of patterns:
 * exact `27498`, prefix `26*` or range `25938...25999`), sort and methods (each id, name,
 * description, gross_price, free_from: goods value from which it is free, age_check_price: no effect
 * any more – indexed titles are never shipped, active, sort).
 */
class AppShipping
{
    /**
     * The app's shipping zones with their methods, in the order they are tried.
     *
     * @return list<object>
     *
     * @throws ApiException
     */
    public function zones(): array
    {
        return AppRequest::send('GET', 'v1/client/shipping-zones')->data;
    }

    /**
     * The zone with its methods, or null if the app has none with this ID.
     *
     * @throws ApiException
     */
    public function zone(string $id): ?object
    {
        try {
            return AppRequest::send('GET', sprintf('v1/client/shipping-zones/%s', $id))->data;
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Creates a shipping zone with its methods.
     *
     * @param array{
     *     name: string,
     *     countries: list<string>,
     *     postal_codes?: null|list<string>,
     *     sort?: int,
     *     methods?: list<array{name: string, gross_price: float|int|string, free_from?: null|float|int|string, age_check_price?: null|float|int|string, description?: null|string, active?: bool, sort?: int}>
     * } $attributes
     *   - countries:    ISO 3166-1 alpha-2 codes, e.g. ["DE"]
     *   - postal_codes: patterns, e.g. ["25980", "26*", "25938...25999"]; empty: the whole countries
     *   - sort:         tried in ascending order; put special zones (islands) first
     *   - methods:      e.g. "DHL Paket" 3.99 (gross), free_from 20.00 (goods value, gross),
     *                   age_check_price: still accepted, no effect (there is no age check on delivery)
     *
     * @throws ApiException 422 for an unknown country
     */
    public function createZone(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/shipping-zones', $attributes)->data;
    }

    /**
     * Changes a zone. `methods`, when given, replaces its methods (entries with `id` are kept).
     *
     * @throws ApiException 404 "shipping_zone_not_found"
     */
    public function updateZone(string $id, array $attributes): object
    {
        return AppRequest::send('PATCH', sprintf('v1/client/shipping-zones/%s', $id), $attributes)->data;
    }

    /**
     * @throws ApiException 404 "shipping_zone_not_found"
     */
    public function deleteZone(string $id): void
    {
        AppRequest::send('DELETE', sprintf('v1/client/shipping-zones/%s', $id));
    }

    /**
     * What shipping costs for an address and goods worth $goodsTotal (gross), e.g. for the cart.
     * $ageCheck is deprecated: indexed titles are never shipped, Anikeen ID answers true with 422.
     *
     * @return object {zone: null|{id, name}, methods: list<{id, name, description, price, free_from}>};
     *                zone null: the app doesn't ship there
     *
     * @throws ApiException
     */
    public function rates(string $countryIso, ?string $postalCode = null, float|int|string $goodsTotal = 0, bool $ageCheck = false): object
    {
        return AppRequest::send('GET', 'v1/client/shipping-rates', null, array_filter([
            'country_iso' => $countryIso,
            'postal_code' => $postalCode,
            'goods_total' => (string) $goodsTotal,
            'age_check' => $ageCheck ? '1' : null,
        ], fn ($value) => $value !== null))->data;
    }
}
