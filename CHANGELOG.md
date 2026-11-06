# Changelog

## Unreleased

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
