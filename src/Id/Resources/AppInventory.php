<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;

/**
 * The app's own product catalog with stock, with the app's own token (scope inventory:client,
 * any app): app(AnikeenId::class)->inventory(). Nothing is requested until a method is called.
 *
 * Each product is an object with id, sku (the app's article number, unique per app), name,
 * description, kind (goods|service, see ProductKind), type (digital|physical|physical-reduced, the
 * order item type), unit (goods: piece|onetime; services: hour|day|week|month|year|onetime|
 * gigabyte|gibibyte), price (net), gross_price (null when priced net), image_url, available,
 * track_inventory, backorderable (also sold without stock), stock_on_hand (physically in stock;
 * negative while backorders wait for the supplier), stock_reserved (held by open orders),
 * stock_blocked (free units in blocked storage bins: in stock, but not for sale),
 * stock_available (on hand minus reserved and blocked, at least 0; null without track_inventory),
 * stock_backordered (units sold beyond the stock, waiting for the supplier), low_stock_threshold,
 * low_stock, storage_location (shelf in the default warehouse), stocks (per warehouse: warehouse_id,
 * stock_on_hand, stock_reserved, stock_blocked, stock_available, storage_location), price_bound
 * (fixed book price: the same in every sales channel), weight_grams, metadata (the app's own data),
 * safety, created_at, updated_at and deleted_at; services also carry family (the app's product
 * family, e.g. "server"), monthly_cap (net, at most this per calendar month; hour and day units),
 * options (the app's plan options, e.g. cores and memory), coupon_required (sold only with a
 * coupon), setup_price / transfer_price (net list prices of a dearer first period, e.g. a domain's
 * registration and transfer) and the purchase prices cost_price, cost_setup_price and
 * cost_transfer_price (net; only the owning app ever sees them – never a sales channel or a
 * customer; no offer, coupon or reseller discount goes below them).
 *
 * Services (kind `service`, e.g. the servers, domains and storage of a hosting app) are sold with
 * subscriptions: `product_id` on AppSubscriptions::create() and checkout sessions, and Anikeen ID
 * computes the price (list price → best offer → reseller discount or coupon, see price()). Meter
 * products (services with unit gigabyte or gibibyte) price usage records
 * (AppSubscription::recordUsage()). Services have no stock and are never price-bound.
 *
 * A sales channel (an app that sells another app's catalog, set up in the Anikeen ID admin) reads
 * that catalog with the same methods, read-only: products of its assortment with its price
 * (price, gross_price, price_bound), stock_available in its warehouses and listed; no stock
 * internals. Its orders name those products with `items.*.product_id`; changing the catalog, the
 * stock or purchasing answers 403 "channel_read_only".
 *
 * `safety` is the product safety information an online offer in the EU has to show (General
 * Product Safety Regulation 2023/988, Art. 19): manufacturer and responsible_person (economic
 * operators, see below, or null), warnings (list of strings), information, min_age and missing
 * (what is still required: "manufacturer", "manufacturer.email", "responsible_person").
 *
 * Each economic operator is an object with id, name, trade_name, address, address_2, postal_code,
 * city, country_iso, email, url, phone and in_eu. An app reuses its operators across products.
 *
 * Orders name products with `items.*.product_id`: Anikeen ID takes what the item leaves out from
 * the product, reserves tracked stock while the order is open, takes it when the order is paid
 * and releases it when the order is cancelled, expires or is deleted.
 *
 * Each stock movement is an object with id, product_id, type (reservation|release|sale|cancellation,
 * booked along orders – cancellation: paid units cancelled before shipping, back in stock; receipt|return|adjustment|damage|count, booked by the app), quantity (change of
 * the stock on hand), reserved_change, stock_on_hand and stock_reserved (after the movement),
 * order_id, order_item_id (returns), reference, note and created_at; warehouse_id and
 * warehouse_stock_on_hand (after the movement, in that warehouse) for catalogs with warehouses.
 */
class AppInventory
{
    /** Products per batch request (Anikeen ID's limit). */
    public const BATCH_SIZE = 100;

    /** quotes(): products per request (Anikeen ID answers at most this many). */
    public const QUOTES_SIZE = 1000;

    /**
     * All matching products, by SKU.
     *
     * @param array{
     *     sku?: string,
     *     search?: string,
     *     available?: bool,
     *     kind?: string,
     *     family?: string,
     *     low_stock?: bool,
     *     updated_since?: string,
     *     with_trashed?: bool
     * } $filters Optional filters:
     *   - sku:           Exactly this SKU
     *   - search:        Part of the name, or the beginning of the SKU
     *   - available:     Only products that can (true) or can't (false) be ordered
     *   - kind:          Only goods or only services (ProductKind::GOODS, ProductKind::SERVICE)
     *   - family:        Only services of this product family, e.g. "server"
     *   - low_stock:     Only tracked products whose available stock is at or below their low_stock_threshold
     *   - updated_since: Only products changed since this date (ISO 8601), e.g. to sync a cache
     *   - with_trashed:  Include deleted products
     * @return list<object>
     *
     * @throws ApiException
     */
    public function products(array $filters = []): array
    {
        return $this->allPages('v1/client/products', $this->query($filters));
    }

    /**
     * The product, or null if the app has none with this ID.
     *
     * @throws ApiException
     */
    public function product(string $id): ?object
    {
        try {
            return AppRequest::send('GET', sprintf('v1/client/products/%s', $id))->data;
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * The app's product with this SKU, or null.
     *
     * @throws ApiException
     */
    public function productBySku(string $sku): ?object
    {
        return $this->products(['sku' => $sku])[0] ?? null;
    }

    /**
     * Creates a product. New goods track their stock; `stock_on_hand` books the initial
     * stock as an inventory count. A service (`kind` service) has no stock: send its unit, and
     * optionally family, monthly_cap, options and coupon_required.
     *
     * @param array{
     *     sku: string,
     *     name: string,
     *     type?: string,
     *     kind?: string,
     *     price?: float|int|string,
     *     gross_price?: float|int|string,
     *     description?: null|string,
     *     unit?: string,
     *     family?: null|string,
     *     monthly_cap?: null|float|string,
     *     options?: null|array,
     *     coupon_required?: bool,
     *     setup_price?: null|float|string,
     *     transfer_price?: null|float|string,
     *     cost_price?: null|float|string,
     *     cost_setup_price?: null|float|string,
     *     cost_transfer_price?: null|float|string,
     *     image_url?: null|string,
     *     available?: bool,
     *     price_bound?: bool,
     *     track_inventory?: bool,
     *     backorderable?: bool,
     *     low_stock_threshold?: null|int,
     *     storage_location?: null|string,
     *     weight_grams?: null|int,
     *     metadata?: null|array,
     *     manufacturer_id?: null|string,
     *     manufacturer?: null|array,
     *     responsible_person_id?: null|string,
     *     responsible_person?: null|array,
     *     safety_warnings?: null|list<string>,
     *     safety_information?: null|string,
     *     min_age?: null|int,
     *     stock_on_hand?: int
     * } $attributes The product:
     *   - sku:                 The app's article number (max. 100 characters), unique per app
     *   - name:                Shown on the order and invoice (max. 255 characters)
     *   - type:                digital, physical or physical-reduced (VAT category of the order item;
     *                          required for goods, services default to digital)
     *   - kind:                goods (default) or service (ProductKind)
     *   - price/gross_price:   Net or gross unit price, one of both
     *   - description:         Shown under the name (max. 255 characters, optional)
     *   - unit:                Goods: piece (default) or onetime. Services (required): hour, day,
     *                          week, month, year, onetime, gigabyte or gibibyte (meter products)
     *   - family:              Services: the app's product family, e.g. "server" ([a-z0-9_.-],
     *                          max. 64); reseller discounts can be per family
     *   - monthly_cap:         Services with unit hour or day: net, at most this per calendar month
     *                          (2 decimals, not below the price; null = no cap)
     *   - options:             Services: the app's plan options, e.g. cores, memory, GPU (any JSON
     *                          object, max. 50 keys)
     *   - coupon_required:     Services: sold only with a coupon naming the product (default false)
     *   - setup_price:         Services: net, the first period of a new subscription costs this on
     *                          top (e.g. a domain registration; see price() and FirstPeriod)
     *   - transfer_price:      Services: net, the first period of a move-in costs at least this
     *                          (e.g. a domain transfer)
     *   - cost_price, cost_setup_price, cost_transfer_price: Services: net purchase prices per unit,
     *                          of the setup and of a transfer (e.g. the registrar's). Never public;
     *                          reductions stop there. Send cost_price = price while it is unknown to
     *                          allow no reduction at all
     *   - image_url:           Picture for orders and invoices (optional)
     *   - available:           Whether it can be ordered (default true)
     *   - price_bound:         Fixed book price (Buchpreisbindung): the same in every sales channel and
     *                          taken over by the price check; default true for a book (ISBN-13 as
     *                          sku, physical-reduced), else false
     *   - track_inventory:     Whether orders reserve and take its stock (default true)
     *   - backorderable:       Also sold without stock: orders reserve the missing units anyway and
     *                          the purchase orders get them from the supplier (default false)
     *   - low_stock_threshold: low_stock is true at or below this available stock (optional)
     *   - storage_location:    Where it lies in the warehouse, e.g. "R3-F2" (max. 100, optional)
     *   - weight_grams:        Its weight in grams, e.g. for shipping (optional)
     *   - metadata:            The app's own data (max. 50 keys, optional)
     *   - manufacturer_id / manufacturer: Product safety (GPSR): the manufacturer, by ID or as an
     *                          operator (see createOperator(); created or updated by name)
     *   - responsible_person_id / responsible_person: The responsible person in the EU, required
     *                          when the manufacturer is outside the EU
     *   - safety_warnings:     Warnings and safety notes in the buyers' language (max. 20, optional)
     *   - safety_information:  Further safety information (max. 5000 characters, optional)
     *   - min_age:             Minimum age, if the product has one (optional)
     *   - stock_on_hand:       The counted stock, booked as an inventory count (optional)
     *
     * @throws ApiException 409 "sku_taken" when the app already has a product with this SKU
     */
    public function createProduct(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/products', $attributes)->data;
    }

    /**
     * Creates or changes products by SKU (a deleted product is restored), e.g. to sync a shop's
     * catalog; sent in batches of 100. A `stock_on_hand` that differs from the stored stock is
     * booked as an inventory count. Each returned product has `created` (true if it was new).
     *
     * @param list<array> $products Products as for createProduct()
     * @return list<object>
     *
     * @throws ApiException a 422 names the row as products.<index in its batch>.<field>
     */
    public function upsertProducts(array $products): array
    {
        $saved = [];

        foreach (array_chunk(array_values($products), self::BATCH_SIZE) as $batch) {
            array_push($saved, ...AppRequest::send('POST', 'v1/client/products/batch', ['products' => $batch])->data);
        }

        return $saved;
    }

    /**
     * Changes fields of a product (any of createProduct(), except stock_on_hand: the stock only
     * changes by stock movements and counts).
     *
     * @throws ApiException 404 "product_not_found", 409 "sku_taken"
     */
    public function updateProduct(string $id, array $attributes): object
    {
        return AppRequest::send('PATCH', sprintf('v1/client/products/%s', $id), $attributes)->data;
    }

    /**
     * What a service costs one customer for a new subscription, as Anikeen ID computes it: the list
     * price, then the best running offer, then the reseller discount of $group (the reseller pays
     * the lower of reseller and offer price, never a coupon on top) or a percentage coupon off the
     * offer price – never below the product's purchase price (`floored` when that stopped it). The
     * monthly cap is cut in the same proportion. A subscription created with the same `product_id`,
     * `group`, `coupon_code` and `first_period` gets this price as its contract price.
     *
     * A service with a setup or transfer price has a dearer first period (`first_period`: the same
     * reduction, never below its own purchase price nor below the renewal price): the subscription
     * starts at `first_period.price` and Anikeen ID changes it to `renewal_price` at the end of that
     * period by itself (its `scheduled_change` with `first_period`). Null when the first period
     * costs what every period costs.
     *
     * @param string|null $userId The customer (offers for one customer; checks coupon uses)
     * @param string|null $group The subscription group, e.g. a team (offers and reseller discounts
     *                           for it)
     * @param string|null $couponCode A coupon the customer entered
     * @param string|null $firstPeriod FirstPeriod::SETUP (default), TRANSFER or NONE
     * @return object {product_id, unit, list_price, list_monthly_cap, price, monthly_cap,
     *                offer: ?{id, name, type, amount}, reseller_discount: ?{id, family, percent},
     *                coupon: ?{id, code, type, amount}, floored, renewal_price,
     *                first_period: ?{kind, list_price, price, floored}} (net prices)
     *
     * @throws ApiException 404 "product_not_found"; 422 with a PricingError code
     *                      (isPricingError()): product_not_a_service, product_unavailable,
     *                      coupon_invalid, coupon_expired, coupon_used_up, coupon_not_applicable,
     *                      coupon_not_allowed, coupon_required; 422 validation without a code for
     *                      an unknown first_period (use the FirstPeriod constants)
     */
    public function price(string $productId, ?string $userId = null, ?string $group = null, ?string $couponCode = null, ?string $firstPeriod = null): object
    {
        return AppRequest::send('GET', sprintf('v1/client/products/%s/price', $productId), null, array_filter([
            'user_id' => $userId,
            'group' => $group,
            'coupon_code' => $couponCode,
            'first_period' => $firstPeriod,
        ], fn ($value) => $value !== null && $value !== ''))->data;
    }

    /**
     * The prices (like price()) of many services for one customer at once, e.g. a price list of
     * every domain TLD: by `product_ids` (answered in that order; more than 1000 are asked in parts)
     * or a whole `family` (its available services, at most 1000, by SKU). A product that can't be sold like that is an
     * entry {product_id, error, message} (a PricingError code), not an exception.
     *
     * @param array{
     *     product_ids?: list<string>,
     *     family?: string,
     *     user_id?: null|string,
     *     group?: null|string,
     *     coupon_code?: null|string,
     *     first_period?: null|string
     * } $attributes product_ids or family (one of both); the rest like price()
     * @return list<object> quotes as price() returns them, or {product_id, error, message}
     *
     * @throws ApiException 422 validation (e.g. an unknown first_period), "too_many_products" (a
     *                      family with more than 1000)
     */
    public function quotes(array $attributes): array
    {
        $attributes = array_filter($attributes, fn ($value) => $value !== null && $value !== '');

        if (! isset($attributes['product_ids'])) {
            return AppRequest::send('POST', 'v1/client/products/quotes', $attributes)->data;
        }

        // more IDs than one request takes: in parts, in the order asked
        $quotes = [];

        foreach (array_chunk(array_values($attributes['product_ids']), self::QUOTES_SIZE) as $ids) {
            array_push($quotes, ...AppRequest::send('POST', 'v1/client/products/quotes', ['product_ids' => $ids] + $attributes)->data);
        }

        return $quotes;
    }

    /**
     * The reseller discounts of the app's services: percent off the list price for a reseller's
     * subscription group (e.g. a team), per product family or (family null) for all services.
     * The family's own discount beats the one for all; of those, the newest already valid.
     *
     * @param array{group?: string, family?: string} $filters Only discounts of this group / family
     * @return list<object> each {id, group, family, percent, valid_from, created_at, updated_at}
     *
     * @throws ApiException
     */
    public function resellerDiscounts(array $filters = []): array
    {
        return $this->allPages('v1/client/reseller-discounts', $filters);
    }

    /**
     * The reseller discount, or null if the app has none with this ID.
     *
     * @throws ApiException
     */
    public function resellerDiscount(string $id): ?object
    {
        return $this->findOrNull(sprintf('v1/client/reseller-discounts/%s', $id));
    }

    /**
     * Gives a reseller's group a discount on the app's services. It applies to subscriptions
     * created from then on; running ones keep their contract price.
     *
     * @param array{group: string, percent: float|int|string, family?: null|string, valid_from?: string} $attributes
     *   - group:      The subscription group of the reseller, e.g. the team ID
     *   - percent:    Off the list price (more than 0, at most 100, 2 decimals)
     *   - family:     Only services of this product family (null: all services)
     *   - valid_from: From this date on (ISO 8601; default now)
     *
     * @throws ApiException 422 validation
     */
    public function createResellerDiscount(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/reseller-discounts', $attributes)->data;
    }

    /**
     * Changes fields of a reseller discount (new subscriptions only).
     *
     * @throws ApiException 404 "reseller_discount_not_found"
     */
    public function updateResellerDiscount(string $id, array $attributes): object
    {
        return AppRequest::send('PATCH', sprintf('v1/client/reseller-discounts/%s', $id), $attributes)->data;
    }

    /**
     * Removes a reseller discount (running subscriptions keep their contract price).
     *
     * @throws ApiException 404 "reseller_discount_not_found"
     */
    public function deleteResellerDiscount(string $id): void
    {
        AppRequest::send('DELETE', sprintf('v1/client/reseller-discounts/%s', $id));
    }

    /**
     * Removes a product from the catalog: it can't be ordered anymore; orders and its stock
     * journal keep it. upsertProducts() with its SKU restores it.
     *
     * @throws ApiException 404 "product_not_found"
     */
    public function deleteProduct(string $id): void
    {
        AppRequest::send('DELETE', sprintf('v1/client/products/%s', $id));
    }

    /**
     * The product's stock journal, newest first.
     *
     * @param array{type?: string} $filters Only movements of this type (optional)
     * @return list<object>
     *
     * @throws ApiException 404 "product_not_found"
     */
    public function stockMovements(string $productId, array $filters = []): array
    {
        return $this->allPages(sprintf('v1/client/products/%s/stock-movements', $productId), $filters);
    }

    /**
     * Books a change of the stock on hand and returns the movement.
     *
     * @param string $type receipt (goods received), return (goods back from a customer), damage
     *                     (taken out) - each with a positive quantity - or adjustment (either sign)
     * @param string|null $reference The app's reference, e.g. a purchase order or delivery note (max. 100 characters)
     * @param string|null $warehouseId The warehouse (default: the catalog's default warehouse)
     *
     * @throws ApiException 404 "product_not_found" or "warehouse_not_found", 422 for a wrong type or sign
     */
    public function bookStock(string $productId, string $type, int $quantity, ?string $reference = null, ?string $note = null, ?string $warehouseId = null): object
    {
        return AppRequest::send('POST', sprintf('v1/client/products/%s/stock-movements', $productId), array_filter([
            'type' => $type,
            'quantity' => $quantity,
            'reference' => $reference,
            'note' => $note,
            'warehouse_id' => $warehouseId,
        ], fn ($value) => $value !== null))->data;
    }

    /**
     * Goods received: adds $quantity to the stock on hand.
     *
     * @throws ApiException
     */
    public function receive(string $productId, int $quantity, ?string $reference = null, ?string $note = null, ?string $warehouseId = null): object
    {
        return $this->bookStock($productId, 'receipt', $quantity, $reference, $note, $warehouseId);
    }

    /**
     * An inventory count: the counted stock on hand replaces the stored one; the difference is
     * booked as a `count` movement. With several warehouses it counts one of them (default: the
     * default warehouse).
     *
     * @throws ApiException
     */
    public function count(string $productId, int $stockOnHand, ?string $reference = null, ?string $note = null, ?string $warehouseId = null): object
    {
        return AppRequest::send('POST', sprintf('v1/client/products/%s/inventory-count', $productId), array_filter([
            'stock_on_hand' => $stockOnHand,
            'reference' => $reference,
            'note' => $note,
            'warehouse_id' => $warehouseId,
        ], fn ($value) => $value !== null))->data;
    }

    /**
     * The product's price history, kept by Anikeen ID (product.updated only carries the current
     * price): every change with its source – admin, api or jpc (the price check of fixed book
     * prices) – and the last price check.
     *
     * @return object {product_id, gross_price, price, price_bound, price_checked_at, price_check_note,
     *                changes: list<{source, previous_gross_price, gross_price, previous_price, price, changed_at}>,
     *                checks: list<{source, outcome, source_gross_price, gross_price, url, checked_at}>}
     *                (newest first; checks are the last 50 price checks, also those without a change)
     *
     * @throws ApiException 404 "product_not_found"
     */
    public function priceHistory(string $productId): object
    {
        return AppRequest::send('GET', sprintf('v1/client/products/%s/prices', $productId))->data;
    }

    /**
     * Moves units between two warehouses of the catalog (Umlagerung): two `transfer` movements,
     * the product's total stays.
     *
     * @return list<object> the movement out of $fromWarehouseId and the one into $toWarehouseId
     *
     * @throws ApiException 404 "warehouse_not_found", 409 "out_of_stock" (fewer available in the source)
     */
    public function transferStock(string $productId, string $fromWarehouseId, string $toWarehouseId, int $quantity, ?string $reference = null, ?string $note = null): array
    {
        return AppRequest::send('POST', sprintf('v1/client/products/%s/stock-transfers', $productId), array_filter([
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'quantity' => $quantity,
            'reference' => $reference,
            'note' => $note,
        ], fn ($value) => $value !== null))->data;
    }

    /**
     * The catalog's warehouses, default first. A catalog without one gets its default warehouse
     * ("Hauptlager", code HL) with its first booking.
     *
     * @return list<object> each {id, name, code, address, country_iso, is_default, active, sort}
     *
     * @throws ApiException
     */
    public function warehouses(): array
    {
        return AppRequest::send('GET', 'v1/client/warehouses')->data;
    }

    /**
     * The warehouse, or null if the catalog has none with this ID.
     *
     * @return object|null {id, name, code, address, country_iso, is_default, active, sort}
     *
     * @throws ApiException
     */
    public function warehouse(string $id): ?object
    {
        return $this->findOrNull(sprintf('v1/client/warehouses/%s', $id));
    }

    /**
     * Creates a warehouse; the first one becomes the default.
     *
     * @param array{name: string, code: string, address?: null|string, country_iso?: null|string, is_default?: bool, active?: bool, sort?: int} $attributes
     *   - code:       short and unique per catalog, e.g. "ML" (shown on pick lists)
     *   - is_default: receives bookings that name no warehouse (exactly one is)
     *   - sort:       order after the default one
     *
     * @throws ApiException 422 for a code that is taken
     */
    public function createWarehouse(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/warehouses', $attributes)->data;
    }

    /**
     * Changes fields of a warehouse.
     *
     * @throws ApiException 404 "warehouse_not_found", 409 "default_required"
     */
    public function updateWarehouse(string $id, array $attributes): object
    {
        return AppRequest::send('PATCH', sprintf('v1/client/warehouses/%s', $id), $attributes)->data;
    }

    /**
     * Removes an empty warehouse that isn't the default.
     *
     * @throws ApiException 404 "warehouse_not_found", 409 "warehouse_not_empty"
     */
    public function deleteWarehouse(string $id): void
    {
        AppRequest::send('DELETE', sprintf('v1/client/warehouses/%s', $id));
    }

    /**
     * Products whose safety information (GPSR) is incomplete, e.g. to fix them before selling.
     *
     * @return list<object>
     *
     * @throws ApiException
     */
    public function productsWithIncompleteSafety(): array
    {
        return $this->products(['safety_incomplete' => true]);
    }

    /**
     * The app's economic operators (manufacturers, responsible persons in the EU), by name.
     *
     * @return list<object>
     *
     * @throws ApiException
     */
    public function operators(?string $search = null): array
    {
        return $this->allPages('v1/client/product-operators', array_filter(['search' => $search]));
    }

    /**
     * The operator, or null if the app has none with this ID.
     *
     * @throws ApiException
     */
    public function operator(string $id): ?object
    {
        try {
            return AppRequest::send('GET', sprintf('v1/client/product-operators/%s', $id))->data;
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Creates an economic operator, or changes the app's operator with the same name.
     *
     * @param array{
     *     name: string,
     *     address: string,
     *     postal_code: string,
     *     city: string,
     *     country_iso: string,
     *     trade_name?: null|string,
     *     address_2?: null|string,
     *     email?: null|string,
     *     url?: null|string,
     *     phone?: null|string
     * } $attributes The operator:
     *   - name:        Name of the company or person
     *   - address:     Street and number
     *   - postal_code: Postal code
     *   - city:        City
     *   - country_iso: ISO 3166-1 alpha-2 country code
     *   - trade_name:  Registered trade name or trademark (optional)
     *   - address_2:   Further address line (optional)
     *   - email:       E-mail address (an email or url is required for a manufacturer)
     *   - url:         Website or contact form
     *   - phone:       Phone number (optional)
     *
     * @throws ApiException
     */
    public function createOperator(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/product-operators', $attributes)->data;
    }

    /**
     * Changes fields of an operator.
     *
     * @throws ApiException 404 "operator_not_found", 409 "name_taken"
     */
    public function updateOperator(string $id, array $attributes): object
    {
        return AppRequest::send('PATCH', sprintf('v1/client/product-operators/%s', $id), $attributes)->data;
    }

    /**
     * Removes an operator; products keep showing it until they get another one.
     *
     * @throws ApiException 404 "operator_not_found"
     */
    public function deleteOperator(string $id): void
    {
        AppRequest::send('DELETE', sprintf('v1/client/product-operators/%s', $id));
    }

    /**
     * @return list<object>
     *
     * @throws ApiException
     */
    private function allPages(string $path, array $query): array
    {
        $rows = [];
        $page = 1;

        do {
            $result = AppRequest::send('GET', $path, null, [...$query, 'per_page' => 100, 'page' => $page]);
            array_push($rows, ...$result->data);
            $lastPage = (int) ($result->meta->last_page ?? $page);
        } while ($page++ < $lastPage);

        return $rows;
    }

    /**
     * The object at $path, or null on 404.
     *
     * @throws ApiException
     */
    private function findOrNull(string $path): ?object
    {
        try {
            return AppRequest::send('GET', $path)->data;
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    private function query(array $filters): array
    {
        return array_map(fn ($value) => is_bool($value) ? (int) $value : $value, $filters);
    }

    /**
     * The app's suppliers (purchasing), by name.
     *
     * @return list<object> each {id, name, customer_number, contact_name, email, phone, url, address,
     *                      country_iso, vat_id, lead_time_days, notes, created_at, updated_at}
     *
     * @throws ApiException
     */
    public function suppliers(?string $search = null): array
    {
        return $this->allPages('v1/client/suppliers', array_filter(['search' => $search]));
    }

    /**
     * The supplier, or null if the app has none with this ID.
     *
     * @throws ApiException
     */
    public function supplier(string $id): ?object
    {
        return $this->findOrNull(sprintf('v1/client/suppliers/%s', $id));
    }

    /**
     * Creates a supplier.
     *
     * @param array{
     *     name: string,
     *     customer_number?: null|string,
     *     contact_name?: null|string,
     *     email?: null|string,
     *     phone?: null|string,
     *     url?: null|string,
     *     address?: null|string,
     *     country_iso?: null|string,
     *     vat_id?: null|string,
     *     lead_time_days?: null|int,
     *     notes?: null|string
     * } $attributes The supplier:
     *   - name:            Unique per app
     *   - customer_number: Your customer number at the supplier
     *   - country_iso:     Decides the tax of its invoices in the accounting (DE, EU, import)
     *   - lead_time_days:  Usual delivery time in days
     *
     * @throws ApiException 409 "name_taken"
     */
    public function createSupplier(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/suppliers', $attributes)->data;
    }

    /**
     * Changes fields of a supplier.
     *
     * @throws ApiException 404 "supplier_not_found"
     */
    public function updateSupplier(string $id, array $attributes): object
    {
        return AppRequest::send('PATCH', sprintf('v1/client/suppliers/%s', $id), $attributes)->data;
    }

    /**
     * Removes a supplier (purchase orders keep it).
     *
     * @throws ApiException 404 "supplier_not_found"
     */
    public function deleteSupplier(string $id): void
    {
        AppRequest::send('DELETE', sprintf('v1/client/suppliers/%s', $id));
    }

    /**
     * The app's purchase orders, newest first.
     *
     * @param array{status?: string, supplier_id?: string} $filters status: draft|ordered|partially_received|received|cancelled
     * @return list<object> each {id, purchase_order_number (E2026-0001), supplier_id, status, ordered_at,
     *                      expected_at, received_at, note, net_total, items: [{id, product_id,
     *                      quantity_ordered, quantity_received, unit_price}], created_at}
     *
     * @throws ApiException
     */
    public function purchaseOrders(array $filters = []): array
    {
        return $this->allPages('v1/client/purchase-orders', $filters);
    }

    /**
     * The purchase order, or null if the app has none with this ID.
     *
     * @throws ApiException
     */
    public function purchaseOrder(string $id): ?object
    {
        try {
            return AppRequest::send('GET', sprintf('v1/client/purchase-orders/%s', $id))->data;
        } catch (ApiException $e) {
            if ($e->isNotFound()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Drafts a purchase order to a supplier.
     *
     * @param array{
     *     supplier_id: string,
     *     items: list<array{product_id: string, quantity: int, unit_price?: null|float|string}>,
     *     expected_at?: null|string,
     *     note?: null|string
     * } $attributes The purchase order:
     *   - items: tracked products of the app; unit_price (net) defaults to the supplier's price of
     *            the product, else its average cost
     *
     * @throws ApiException 404 "supplier_not_found", 422 "product_not_found"
     */
    public function createPurchaseOrder(array $attributes): object
    {
        return AppRequest::send('POST', 'v1/client/purchase-orders', $attributes)->data;
    }

    /**
     * Marks a draft as ordered.
     *
     * @throws ApiException 409 "invalid_status"
     */
    public function orderPurchaseOrder(string $id): object
    {
        return AppRequest::send('POST', sprintf('v1/client/purchase-orders/%s/order', $id))->data;
    }

    /**
     * Books a delivery: the received units per item (at most what is outstanding). Each unit is a
     * stock receipt with the purchase order number as reference; the average cost is updated.
     *
     * @param array<string, int> $quantities purchase order item ID => received units
     *
     * @throws ApiException 409 "invalid_status"
     */
    public function receivePurchaseOrder(string $id, array $quantities, ?string $deliveryNote = null): object
    {
        return AppRequest::send('POST', sprintf('v1/client/purchase-orders/%s/receive', $id), array_filter([
            'items' => array_map(fn ($itemId, $quantity) => ['id' => $itemId, 'quantity' => $quantity], array_keys($quantities), $quantities),
            'delivery_note' => $deliveryNote,
        ], fn ($value) => $value !== null))->data;
    }

    /**
     * Cancels a draft or an order nothing was delivered for.
     *
     * @throws ApiException 409 "invalid_status"
     */
    public function cancelPurchaseOrder(string $id): object
    {
        return AppRequest::send('POST', sprintf('v1/client/purchase-orders/%s/cancel', $id))->data;
    }
}
