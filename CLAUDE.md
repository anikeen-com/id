# CLAUDE.md

Guidance for Claude Code (and other contributors) working in this repository.

## What this is

`anikeen/id` is the public PHP client for the Anikeen ID API, shipped as a Laravel package
(Laravel 11–13, PHP 8.2+). It covers OAuth/Socialite login, an API token guard, and the billing
API (subscriptions, orders, checkout sessions, shipments, invoices, webhooks). This is a public
repo published on Packagist: never commit secrets, internal hostnames or customer data.

## Commands

```shell
composer install
composer test                                   # vendor/bin/phpunit
vendor/bin/phpunit --filter AppSubscriptionsTest
composer docs                                   # regenerate README.md from README.stub
```

CI (`.github/workflows/tests.yml`) runs PHPUnit on PHP 8.3 and 8.4.

## Layout

- `src/Id/AnikeenId.php` – the API client (singleton, facade `AnikeenId`). Reads
  `config('services.anikeen.*')`; `mode=staging` switches to the staging base URL unless
  `base_url` is set. `request()` wraps Guzzle and returns a `Result`.
- `src/Id/Billable.php` – trait for the app's user model; composes the `Concerns/Manages*` traits.
  Its API caches are declared properties on purpose (undeclared ones would be treated as Eloquent
  attributes and break `save()`). Add a new cache property there when a concern caches.
- `src/Id/Concerns/` – one trait per API area. Traits on `AnikeenId` act as the app/client;
  traits on `Billable` act for one user.
- `src/Id/Resources/` – `BaseResource` (single object) and `BaseCollection` (lists, built via
  `::builder()`). Failed requests throw `ApiException::fromResult()` (`CollectionException` for
  lists), no response `::unreachable()`, `confirmation_required` a `ConfirmationRequiredException`.
  All extend `ResourceException`.
- `src/Id/Resources/App*` – "app" variants that use the app's own client-credentials token
  (`AnikeenId::asApp()`, `Support/AppRequest`) instead of the user's token, for trusted apps.
  They must not send any request until a method is called.
- `src/Id/Auth/`, `Providers/AnikeenIdServiceProvider.php`, `Http/Middleware/` – the `anikeen`
  guard driver and user provider, JWT validation (`Helpers/JwtParser.php` with the bundled
  `oauth-public*.key`), scope middleware.
- `src/Id/Webhooks/` – signature verification (`Webhook::constructEvent`) and `EventType`
  constants.
- `src/Support/Query.php` – query-string builder.
- `generator/generate-docs.php` – reflects the public methods of `AnikeenId` and `Billable` traits
  into the README.

## Conventions

- **README:** edit `README.stub`, never `README.md` directly, then run `composer docs`. The
  `<!-- GENERATED-DOCS -->` marker is replaced with the method reference. Commit both files.
- **New endpoints:** add a method to the matching `Concerns/Manages*` trait (or a resource class),
  document request attributes with an `array{…}` PHPDoc shape and a short description per key,
  and add an example to `README.stub` when it is a new feature.
- **Tests:** extend `Anikeen\Id\Tests\TestCase` (Orchestra Testbench). No live API: queue
  responses with `$this->respond($status, $body)` (Guzzle `MockHandler`) and assert the sent
  requests via `$this->sent` / `$this->sentJson($i)` – method, path, `Authorization` header and
  JSON body. The app token is faked as `app-token`.
- **Compatibility:** this is a library used by several apps. Keep public method signatures
  backwards compatible; add optional parameters instead of changing existing ones.
- Code, comments, docs and commit messages in English. Commit subjects are short, imperative,
  sentence case (e.g. "Ship orders: shipments with delivery notes"). Work happens on feature
  branches merged via PR into `main`.
