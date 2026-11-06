# Changelog

## Unreleased

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
