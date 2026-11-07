# Changelog

## Unreleased

User profiles and the user and preference webhooks; they need an Anikeen ID release that supports
them.

### Added

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

## 3.4.0-beta.1 (2026-11-06)

Preferences, charges and the billing limit; they need an Anikeen ID release that supports them.

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
