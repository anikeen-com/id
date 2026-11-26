# Changelog

## Unreleased

### Changed

- Age check on delivery is an option per app (envoyr/anikeen-tasks#190): `age_check` on orders,
  `rates(..., ageCheck: true)` and `age_check_price` are no longer deprecated – they work for apps
  where Anikeen ID enabled the age check (Anikeen's admins switch it on); other apps get 422 on
  `age_check`/`ageCheck` (replaces the withdrawal of envoyr/anikeen-tasks#186). Docs only, no code
  change.

## 3.11.0-beta.1 (2026-11-26)

Age check on delivery (envoyr/anikeen-tasks#171); needs the identity release with `age_check`
(on staging).

### Added

- `AppShipping::rates()` takes `ageCheck`: only methods offering the age check on delivery, priced
  with its surcharge. Methods document `age_check_price` (gross surcharge, `null` = not offered).
- Orders document `age_check` (the carrier hands the parcel only to an adult, e.g. DHL
  Alterssichtprüfung 18; only methods offering it, surcharge always charged; none for the address →
  409 `shipping_unavailable`).
- Checkout sessions document `withdrawal_waiver_required` (consumers request the early start on the
  confirmation page; Anikeen ID accepts it already) – envoyr/anikeen-tasks#169.

## 3.10.0 (2026-11-23)

Small additions for what Anikeen ID already offers; `isPriceChanged()` needs
anikeen-com/identity#183 (released). Same as 3.10.0-beta.1.

### Added

- `AppShipping::zone()` (`GET client/shipping-zones/{id}`), `AppInventory::warehouse()`
  (`GET client/warehouses/{id}`) and `AppInventory::supplier()` (`GET client/suppliers/{id}`): one
  zone, warehouse or supplier, or `null` when the app has none with this ID.
- Orders document `free_shipping` (the app grants the shipping of an order with
  `calculate_shipping`: the method stays, at 0 €) and `ship_after` (the warehouse holds the goods
  back until then, so a customer's orders leave in one parcel). Anikeen ID accepts both already.
- Products and their `stocks` document `stock_blocked`: free units in blocked storage bins, in stock
  but not for sale; `stock_available` is on hand minus reserved and blocked.
- `ApiException::isPriceChanged()`: an order item named a price-bound product (Buchpreisbindung) of
  the app's own catalog at another price than its catalog price (`422` `price_changed`, nothing
  created). `body()` has the catalog prices of the first product (`product_id`, `sku`, `price`,
  `gross_price`) and all refused items in `items`. Needs anikeen-com/identity#183
  (released); before it, the app's own price-bound products took any given price.
- README: collected shipping, `stock_blocked`, selling price-bound products, and a generated
  reference of the resources (`AppInventory`, `AppShipping`, `AppOrders`, `AppSubscriptions`,
  `AppCheckoutSessions`, `AppCharge` …), which `composer docs` now reflects next to the traits.

## 3.9.0 (2026-11-21)

Shipping labels: void a label, retry a purchase safely, read the label list; products can be
backorderable. `voidShippingLabel()` and `reference` need anikeen-com/identity#164, backorders
anikeen-com/identity#172 (both released).

### Added

- `voidShippingLabel()` (`POST shipping-labels/{id}/void`): a label that will not be posted. An
  Internetmarke stamp is refunded through Deutsche Post's retoure, a dry run label only marked.
  `409` `label_cancelled`, `label_shipped`, `label_not_refundable`, `label_busy`; `502`
  `refund_failed`, nothing changed.
- `reference` on `issueShippingLabel()`: the app's own ID for the label, e.g. its envelope's. A retry
  with it gets the live label already issued (`200`) instead of a second stamp; a different
  purchase under it answers `409` `reference_conflict` with `label_id`, and `409` `label_busy`
  while one with it is still running. Voiding the label frees the reference.
- `shippingLabels()` takes `reference` (the labels issued under it) and `page`.
- README: shipping labels.
- Products: `backorderable` (also sold without stock: orders reserve the missing units anyway, the
  purchase orders get them from the supplier) and `stock_backordered` (units sold beyond the
  stock). `stock_on_hand` can be negative while backorders wait. Needs an Anikeen ID release with
  backorders.

### Changed

- `issueShippingLabel()` documents `test` as Anikeen ID treats it now: a dry run, nothing bought or
  billed, `provider: test` with a placeholder PDF.

### Fixed

- `Result` reads Laravel's plain paginator (`links` as a list, the page data at the root), which
  `shippingLabels()` gets: it threw a `TypeError` (#35). `links` and `meta` come out in the shape of
  a resource collection.

## 3.8.0 (2026-11-19)

Shipping zones per app and price-bound products; needs an Anikeen ID release with both.

### Added

- `AnikeenId::shipping()` (`AppShipping`, app token, scope `Scope::INVENTORY_CLIENT`): the app's
  shipping zones (`zones()`, `createZone()`, `updateZone()`, `deleteZone()`) and what shipping
  costs for an address (`rates()`). Orders take `calculate_shipping` (Anikeen ID prices the
  shipping by the zones and the shipping address, the customer picks the method on the
  confirmation page; VAT follows the goods) and `shipping_method_id`. Needs an Anikeen ID release
  with shipping zones.
- `price_bound` on `createProduct()`, `upsertProducts()` and `updateProduct()`, and in every
  product (Buchpreisbindung). A new book (ISBN-13 as `sku`, `physical-reduced`) is price-bound
  unless the app sends `price_bound: false`; a change sends `product.updated`. Needs an Anikeen ID
  release with this field.

## 3.7.0 (2026-11-18)

The app's own product catalog with stock; needs an Anikeen ID release with the inventory API.

### Added

- `AnikeenId::inventory()` (`AppInventory`, app token, scope `Scope::INVENTORY_CLIENT`):
  `products()`, `product()`, `productBySku()`, `createProduct()`, `upsertProducts()` (batches of
  100 by SKU), `updateProduct()`, `deleteProduct()`, `stockMovements()`, `bookStock()`,
  `receive()` and `count()`.
- Order items take `product_id` of an app product: the item takes what it leaves out from the
  product and its tracked stock is reserved, taken on payment and released on cancellation.
- `ApiException::isOutOfStock()` for `409` `out_of_stock` (details in `body()`).
- Orders have a readable `order_number`, consecutive per year (`B2026-0001`).
  `$user->orders(['own' => true])` lists only the orders the calling app created,
  `['order_number' => …]` finds one by its number; `AppOrders::findByNumber()` with the app token.
  `orders()` keeps one list per parameter set (it returned the first list for any parameters).
- `AppInventory::priceHistory()`: a product's price history kept by Anikeen ID (sources admin, api,
  jpc = the price check of fixed book prices); product.updated carries only the current price.
- `Scope::INVENTORY_STAFF` (`inventory-staff`): Anikeen's staff in the inventory app (user token,
  administrators only).
- Warehouses: `AppInventory::warehouses()`, `createWarehouse()`, `updateWarehouse()`,
  `deleteWarehouse()`, `transferStock()`; `bookStock()`, `receive()` and `count()` take an optional
  `$warehouseId`. Products carry `stocks` (per warehouse) and `price_bound`; movements carry
  `warehouse_id`.
- Sales channels: an app selling another app's catalog reads it with the same `AppInventory`
  methods (its assortment, its prices, its warehouses' stock) and orders from it.
- Purchasing: `AppInventory::suppliers()`, `createSupplier()`, `updateSupplier()`, `deleteSupplier()`,
  `purchaseOrders()`, `purchaseOrder()`, `createPurchaseOrder()`, `orderPurchaseOrder()`,
  `receivePurchaseOrder()` (books stock receipts) and `cancelPurchaseOrder()`.
- Stock movements carry `order_item_id` (returns booked per order item).
- Webhook events `EventType::PRODUCT_UPDATED` (`product.updated`) and `PRODUCT_STOCK_CHANGED`
  (`product.stock_changed`) for the app's catalog; opt-in. `PRODUCT_STOCK_LOW` (`product.stock_low`) when a
  product falls to its low stock threshold.
- Products take `storage_location` and `weight_grams`; order items in webhooks and app orders carry
  `product_id`.
- `Scope::INVENTORY_CLIENT` (`inventory:client`).
- Product safety information (EU General Product Safety Regulation): products take a
  `manufacturer` and `responsible_person` (by ID or inline), `safety_warnings`,
  `safety_information` and `min_age`, and return them as `safety` with what is still `missing`.
  `AppInventory::operators()`, `operator()`, `createOperator()`, `updateOperator()`,
  `deleteOperator()` and `productsWithIncompleteSafety()` for the app's economic operators.

## 3.6.0 (2026-11-09)

Scopes and guard support for apps acting in the Anikeen Console for a user; the `client` claim and
`app_access.revoked` need an Anikeen ID release that sends them.

### Added

- Scopes per product family of the Anikeen Console, full and `:read`: `Scope::CONSOLE_SERVERS`,
  `CONSOLE_CONTAINERS`, `CONSOLE_DOMAINS`, `CONSOLE_ZONES`, `CONSOLE_HOSTING`,
  `CONSOLE_LOAD_BALANCERS` and `CONSOLE_OBJECT_STORAGE` (`console-servers`, …).
- `AnikeenId::provisionUsersUsing()`: the `anikeen` guard lets a callback create an unknown user of a
  valid bearer token (with the token's claims and the user's profile) instead of answering `401`.
  With `verify_revocation` the profile of that check is reused, otherwise it is read once.
- `HasAnikeenTokens::anikeenTokenClientId()`, `anikeenTokenClientName()` and
  `anikeenTokenClientTrusted()` and `anikeenTokenIsPersonalAccess()` from the token's `aud` and new
  `client` claim (`name`, `trusted`, `personal_access`); the guard also sets
  the request attribute `oauth_client_name`.
- `AnikeenId::actingAs()` takes further token claims as an optional fourth argument.
- `EventType::APP_ACCESS_REVOKED` (`app_access.revoked`, opt-in): a user removed an app's access, or
  Anikeen revoked the app or no longer trusts it.
- Guzzle 8 is allowed (`guzzlehttp/guzzle: ^7.8|^8.0`), so the package installs in a new Laravel 13 app.

### Changed

- Anikeen ID enforces scopes on every call with a user's token (first only logged, then refused
  with `403` `missing_scope`). Reads need `<area>:read` or `<area>`, changes `<area>`; `user` and
  `*` pass all: `user:read` (user, Socialite, `vatRate()`, `balance()`), `user` (profile changes),
  `billing:read` / `billing` (overview; `charge()`, `billingPortalUrl()`), `addresses:read` /
  `addresses`, `invoices:read` (or `billing:read`), `transactions:read`, `orders:read` / `orders`
  (also items and shipments), `payment-methods:read` / `payment-methods`, `subscriptions:read` /
  `subscriptions` (also checkout sessions), `ssh-keys:read` / `ssh-keys`. The README lists the
  scope of each method. Without `user:read`, `getAuthedUser()` returns only the user's `id`.

### Fixed

- The `anikeen` guard treats a token whose `sub` equals its `aud` (a client credentials token, as Passport issues them) as one without a user, instead of looking up a user by the client id.

## 3.5.0 (2026-11-08)

The user's role for trusted apps; it needs an Anikeen ID release that sends it.

### Added

- `Enums\Role` (`ADMIN`, `USER`): the user's Anikeen ID role, seen by trusted apps only.
- The Socialite user carries it as `$user->role` (`null` for other apps).
- `Resources\User::role()` and `isAdmin()`, for a user read by ID (`findUserById()`) and the
  `data.object` of `user.updated`, which for trusted apps carries `role` (and `role` in `changed`).

## 3.4.0 (2026-11-07)

Preferences, charges, the billing limit, user profiles and the user and preference webhooks; they
need an Anikeen ID release that supports them.

### Added

- Preferences of any user with the app's token (trusted apps, registered by Anikeen per
  namespace): `preferencesByUserId()`, `preferenceByUserId()`, `setPreferenceByUserId()`,
  `deletePreferenceByUserId()` and `findUserIdByPreference()` (the user holding a value of a unique
  key, `null` when nobody does).
- The user's shared preferences with their token: `globalPreferences()`, `globalPreference()`,
  `setGlobalPreference()` and `deleteGlobalPreference()` on `Billable`, cached per instance.
- `Resources\Preference` and `Resources\Preferences`; `Enums\SharedPreference` and
  `Enums\Appearance`.
- Conditional writes: `ifVersion` (`If-Match`) and `createOnly` (`If-None-Match: *`);
  `PreconditionFailedException` (a subclass of `ApiException`) for 412 with `currentVersion()`.
- Scopes `Scope::PREFERENCES`, `PREFERENCES_READ` and `PREFERENCES_CLIENT`.
- Webhook event `order.refunded` (`EventType::ORDER_REFUNDED`).
- Charges on a user's monthly bill for trusted apps: `createChargeForUser()` (with an idempotency
  key), `findChargeForUser()` and `voidChargeForUser()`; `Resources\AppCharge`.
- The user's billing limit for trusted apps: `billingLimitForUser()` and `Resources\BillingLimit`
  (`allows()` is advisory); the `billing_limit` key of `billingOverview()` is documented.
- `ApiException::isBillingLimitReached()` for 409 `billing_limit_reached`.
- Opt-in webhook events `billing_limit.reached` and `billing_limit.available`
  (`EventType::BILLING_LIMIT_REACHED`, `BILLING_LIMIT_AVAILABLE`).
- An optional trailing `$headers` argument on `AnikeenId::request()`, `get()`, `post()`, `put()`,
  `delete()` and `AppRequest::send()`; calls without it send the same request as before.
- Profile updates with the user's token (scope `user`): `updateProfile()` (name, username, email;
  a new email address waits for the user's confirmation), `uploadAvatar()` and `removeAvatar()` on
  `Billable`. They return the updated profile and clear the cached `getUserData()`.
- Reading a connected user's profile by ID for trusted apps: `findUserById()` (`null` for an
  unknown, deleted or unconnected user).
- `Resources\User` with `emailVerified()`, `version()` (`profile_version` or a webhook's
  `version`), `pendingEmail()` and `pendingEmailExpiresAt()`; it also reads the object of a
  `user.updated` event.
- `ValidationFailedException` (a subclass of `ApiException`) for 422 responses, with
  `errorCodes()`, `errorCodeFor()`, `hasErrorCode()` and `errors()`; catching `ApiException` keeps
  working.
- `AnikeenId::requestMultipart()` for file uploads.
- Opt-in webhook events `user.updated`, `user.deleted` and `preference.updated`
  (`EventType::USER_UPDATED`, `USER_DELETED`, `PREFERENCE_UPDATED`).

## 3.3.0 (2026-11-06)

App billing features; they need an Anikeen ID release that supports them.

- Free trials: `trial_days` / `trial_ends_at` (optional `trial_once`, `trial_payment_method` with
  `TrialPaymentMethod::REQUIRED` / `OPTIONAL`) when creating subscriptions and checkout sessions;
  `Subscription::isTrial()`.
- Discounts per billed period: `discount` with `percent_off` or `amount_off`, `duration` and `label`.
- Plan changes of running subscriptions: `AppSubscription::change()` (`apply: now` or
  `period_end`) and `AppSubscription::cancelScheduledChange()`.
- App orders: order lines with subscription, period, net amount and discount;
  `AppOrders::invoicePdf()`. Confirmable orders of trusted apps (`confirm: true`).
- Billing portal links that work without a login: `createBillingPortalLinkForUser()` and
  `appBillingPortalUrl()`.
- Webhook events `subscription.trial_will_end`, `subscription.discount_ended`,
  `subscription.updated` and `subscription.renewed` (`EventType::SUBSCRIPTION_*`);
  `data.reason` with `CancellationReason::TRIAL_ENDED_WITHOUT_PAYMENT_METHOD`.

## 3.2.0 (2026-11-06)

Elapsed-period billing and user lookup; they need an Anikeen ID release that supports them.

- `AppSubscription::checkout()` takes `billElapsedPeriods` and `maxNetAmount`: a subscription
  started more than one period ago is billed for its elapsed periods too, up to the given net
  amount (409 `elapsed_amount_exceeded` with `net_amount`, `elapsed_periods_not_allowed`). Calls
  without the new arguments send the same request as before.
- `ApiException::body()` returns the JSON body of Anikeen ID's error response, e.g. its
  `net_amount`.
- Add `AnikeenId::findUserByEmail()` for trusted apps: looks up an account by email address
  (regardless of case) without creating one, null when there is none.
- `createUser()` documents `name`, `password_confirmation` and `send_invitation` (a newly created
  account gets an invitation mail to set a password) and the response fields `created` and
  `invitation_sent`.

## 3.1.0 (2026-11-05)

- Add the scope `products:read` (`Scope::PRODUCTS_READ`) for read-only access to the product APIs
  of the Anikeen Console.
- Add the reseller scopes `reseller-customers:read`, `reseller-customers`, `reseller-grants` and
  `reseller-orders` (`Scope::RESELLER_CUSTOMERS_READ`, `Scope::RESELLER_CUSTOMERS`,
  `Scope::RESELLER_GRANTS`, `Scope::RESELLER_ORDERS`) for the reseller API of the Anikeen Console.
- Document the scope schema and the new scopes in the README.

Older releases: see the [GitHub releases](https://github.com/anikeen-com/id/releases) and tags.
