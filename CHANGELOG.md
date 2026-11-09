# Changelog

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
