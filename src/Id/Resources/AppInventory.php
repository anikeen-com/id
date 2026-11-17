<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Support\AppRequest;

/**
 * The app's own product catalog with stock, with the app's own token (scope inventory:client,
 * any app): app(AnikeenId::class)->inventory(). Nothing is requested until a method is called.
 *
 * Each product is an object with id, sku (the app's article number, unique per app), name,
 * description, type (digital|physical|physical-reduced, the order item type), unit
 * (piece|onetime), price (net), gross_price (null when priced net), image_url, available,
 * track_inventory, stock_on_hand (physically in stock), stock_reserved (held by open orders),
 * stock_available (on hand minus reserved; null without track_inventory), low_stock_threshold,
 * low_stock, storage_location (shelf in the default warehouse), stocks (per warehouse: warehouse_id,
 * stock_on_hand, stock_reserved, stock_available, storage_location), price_bound (fixed book
 * price: the same in every sales channel), weight_grams, metadata (the app's own data), safety,
 * created_at, updated_at and deleted_at.
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
 * Each stock movement is an object with id, product_id, type (reservation|release|sale, booked
 * along orders; receipt|return|adjustment|damage|count, booked by the app), quantity (change of
 * the stock on hand), reserved_change, stock_on_hand and stock_reserved (after the movement),
 * order_id, order_item_id (returns), reference, note and created_at; warehouse_id and
 * warehouse_stock_on_hand (after the movement, in that warehouse) for catalogs with warehouses.
 */
class AppInventory
{
    /** Products per batch request (Anikeen ID's limit). */
    public const BATCH_SIZE = 100;

    /**
     * All matching products, by SKU.
     *
     * @param array{
     *     sku?: string,
     *     search?: string,
     *     available?: bool,
     *     low_stock?: bool,
     *     updated_since?: string,
     *     with_trashed?: bool
     * } $filters Optional filters:
     *   - sku:           Exactly this SKU
     *   - search:        Part of the name, or the beginning of the SKU
     *   - available:     Only products that can (true) or can't (false) be ordered
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
     * Creates a product. New products track their stock; `stock_on_hand` books the initial
     * stock as an inventory count.
     *
     * @param array{
     *     sku: string,
     *     name: string,
     *     type: string,
     *     price?: float|int|string,
     *     gross_price?: float|int|string,
     *     description?: null|string,
     *     unit?: string,
     *     image_url?: null|string,
     *     available?: bool,
     *     track_inventory?: bool,
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
     *   - type:                digital, physical or physical-reduced (VAT category of the order item)
     *   - price/gross_price:   Net or gross unit price, one of both
     *   - description:         Shown under the name (max. 255 characters, optional)
     *   - unit:                piece (default) or onetime
     *   - image_url:           Picture for orders and invoices (optional)
     *   - available:           Whether it can be ordered (default true)
     *   - track_inventory:     Whether orders reserve and take its stock (default true)
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
