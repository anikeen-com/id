# Anikeen ID

[![Latest Stable Version](https://img.shields.io/packagist/v/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)
[![Total Downloads](https://img.shields.io/packagist/dt/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)
[![License](https://img.shields.io/packagist/l/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)

PHP Anikeen ID API Client for Laravel 11+

Sign in with Anikeen ID and let your users pay with their Anikeen ID account: one-off orders,
subscriptions and physical goods, with invoices, VAT and payment handled by Anikeen ID.

## Table of contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Sign in with Anikeen ID](#sign-in-with-anikeen-id)
4. [Payments](#payments)
5. [Trusted apps](#trusted-apps)
6. [API authentication](#api-authentication)
7. [General](#general)
8. [Examples](#examples)
9. [Documentation](#documentation)
10. [Development](#development)

## Installation

```
composer require anikeen/id
```

## Configuration

Register your app in Anikeen ID first. You need:

- an OAuth client with your callback URL as redirect URI (e.g. `https://shop.test/auth/callback`),
- a webhook endpoint (e.g. `https://shop.test/webhooks/anikeen`) if you sell something; Anikeen ID
  shows its signing secret `whsec_…`,
- optionally a logo, shown to your users on the login and confirmation pages.

Add the credentials to your `.env` file:

```
ANIKEEN_ID_KEY=
ANIKEEN_ID_SECRET=
ANIKEEN_ID_CALLBACK_URL=http://localhost/auth/callback
ANIKEEN_ID_WEBHOOK_SECRET=
```

To switch from `production` to `staging` use following variable:

```
ANIKEEN_ID_MODE=staging
```

You will need to add an entry to the services configuration file so that after config files are cached for usage in production environment (Laravel command `artisan config:cache`) all config is still available.

Add to `config/services.php` file:

```php
'anikeen' => [
    'mode' => env('ANIKEEN_ID_MODE'),
    'client_id' => env('ANIKEEN_ID_KEY'),
    'client_secret' => env('ANIKEEN_ID_SECRET'),
    'redirect' => env('ANIKEEN_ID_CALLBACK_URL'),
    'base_url' => env('ANIKEEN_ID_BASE_URL'),
    'webhook_secret' => env('ANIKEEN_ID_WEBHOOK_SECRET'),
],
```

## Sign in with Anikeen ID

### Event Listener

In Laravel 11, the default EventServiceProvider provider was removed. Instead, add the listener using the listen method on the Event facade, in your `AppServiceProvider` boot method:

```php
public function boot(): void
{
    Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
        $event->extendSocialite('anikeen', \Anikeen\Id\Socialite\Provider::class);
    });
}
```

### User model

Your users table needs the user's Anikeen ID and their tokens:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('anikeen_id')->nullable()->unique();
    $table->text('anikeen_id_access_token')->nullable();
    $table->text('anikeen_id_refresh_token')->nullable();
});
```

Add the `Billable` trait to the user model. Keep the tokens encrypted and out of JSON:

```php
use Anikeen\Id\Billable;

class User extends Authenticatable
{
    use Billable;

    protected $hidden = ['password', 'remember_token', 'anikeen_id_access_token', 'anikeen_id_refresh_token'];

    protected function casts(): array
    {
        return [
            'anikeen_id_access_token' => 'encrypted',
            'anikeen_id_refresh_token' => 'encrypted',
        ];
    }
}
```

All user methods (`$user->orders()`, `$user->subscriptions()`, …) call the API with the user's
access token from `anikeen_id_access_token`.

### Login and callback

Ask for the scopes your app needs (see [Scope](src/Id/Enums/Scope.php)). To sell orders and
subscriptions, a shop typically asks for:

```php
use Laravel\Socialite\Facades\Socialite;

Route::get('auth/redirect', fn () => Socialite::driver('anikeen')
    ->scopes(['user:read', 'addresses:read', 'orders', 'subscriptions'])
    ->redirect());

Route::get('auth/callback', function () {
    $account = Socialite::driver('anikeen')->user();

    $user = User::updateOrCreate(['anikeen_id' => $account->getId()], [
        'name' => $account->getName() ?: $account->getNickname(),
        'email' => $account->getEmail(),
        'anikeen_id_access_token' => $account->token,
        'anikeen_id_refresh_token' => $account->refreshToken,
    ]);

    Auth::login($user, remember: true);

    return redirect()->intended('/');
});
```

Access tokens expire. Get a new one with the refresh token (or send the user through the login
again):

```php
$result = app(\Anikeen\Id\AnikeenId::class)->refreshToken($user->anikeen_id_refresh_token);

if ($result->success()) {
    $user->update([
        'anikeen_id_access_token' => $result->data()->access_token,
        'anikeen_id_refresh_token' => $result->data()->refresh_token,
    ]);
}
```

### Change the default access token / refresh token field name

If your access / refresh token fields differ from the default `anikeen_id_access_token` / `anikeen_id_refresh_token`, you can specify the field name in the `AppServiceProvider` boot method:

```php
use Anikeen\Id\AnikeenId;

public function boot(): void
{
    AnikeenId::useAccessTokenField('anikeen_id_access_token');
    AnikeenId::useRefreshTokenField('anikeen_id_refresh_token');
}
```

## Payments

### Which way fits?

| You want to sell | Use | Token | Who starts the payment |
|---|---|---|---|
| One-off purchases (digital or physical goods) | [Orders](#selling-an-order) | user | the user, on Anikeen ID |
| Something that recurs (a pass, a plan) | [Subscriptions](#subscriptions) | user | the user, on Anikeen ID |
| Several subscriptions in one step | [Checkout sessions](#checkout-sessions-the-user-confirms-several-subscriptions-at-once) | user or app | the user, on Anikeen ID |
| Usage billing from your backend, without the user online | [App subscriptions](#app-subscriptions-trusted-apps-no-user-token) | app | your app (trusted apps only) |

Most apps are third-party apps: they create an order or subscription as a draft, send the user to
Anikeen ID to confirm and pay, and get the user back afterwards. Anikeen ID shows the items, asks
for missing addresses, takes the payment and writes the invoice. Trusted apps (operated by
Anikeen) can also charge directly; see [Trusted apps](#trusted-apps).

The flow, as in a typical shop:

1. The user signs in with Anikeen ID ([above](#sign-in-with-anikeen-id)).
2. Your app creates the order: `$user->orders()->create([...])`.
3. Redirect the user to `$order->confirmationUrl($returnUrl)`.
4. The user confirms and pays on Anikeen ID, then comes back to `$returnUrl?status=…&order=…`.
5. Your app reads the order through the API (never trust the query string) and shows the result.
6. The `order.completed` webhook arrives; fulfil the order there.

### Selling an order

```php
use Anikeen\Id\Exceptions\ResourceException;

$order = $user->orders()->create([
    'billing_address_id' => $user->getUserData()->billing_address_id,
    'items' => [
        ['type' => 'digital', 'name' => 'Sticker pack', 'unit' => 'piece', 'units' => 1, 'gross_price' => '9.99'],
    ],
]);

$shopOrder = ShopOrder::create(['user_id' => $user->id, 'anikeen_id' => $order->id, 'status' => $order->status]); // your own order model

return redirect()->away($order->confirmationUrl(route('orders.return', $shopOrder)));
// later: https://shop.test/orders/1/return?status=completed&order=01J…
```

When the user has no billing address on Anikeen ID yet, pass one instead of the ID. A name and
the country are enough; the user completes it on the confirmation page:

```php
'billing_address' => ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_iso' => 'DE', 'email' => $user->email],
```

Back in your app, read the order to know its state:

```php
Route::get('orders/{shopOrder}/return', function (ShopOrder $shopOrder) {
    $order = auth()->user()->orders()->find($shopOrder->anikeen_id);

    $shopOrder->update(['status' => $order->status]);

    return redirect()->route('orders.show', $shopOrder);
});
```

The order's `status`:

| `status` | Meaning |
|---|---|
| `draft` | Created, not confirmed by the user yet |
| `pending` | Confirmed, the payment is still processing |
| `completed` | Paid |
| `cancelled` | The user cancelled, or it wasn't confirmed within 24 hours |

`return_url` must have the origin of one of your app's redirect URIs. The `status` in its query
string (`completed`, `pending` or `cancelled`) is only a hint for the message you show.

`checkout()` on such a draft throws `ConfirmationRequiredException`; its `confirmationUrl`
points to the same page. Trusted apps keep checking out directly.

### Prices and VAT

Anikeen ID calculates VAT from the user's billing address and the item `type`:

| `type` | For | VAT in Germany |
|---|---|---|
| `digital` | Downloads, streams, services | 19 % |
| `physical` | Goods you ship | 19 % |
| `physical-reduced` | Goods with the reduced rate (books, event tickets, …) | 7 % |

Give each item either `gross_price` (VAT included, what the user pays) or `price` (net), per unit.
`unit` is `piece` or `onetime` for orders; `units` is the quantity. Subscription prices are always
net per unit (`hour`, `day`, `week`, `month`, `year`).

To show a total before the user confirms, ask for a preview:

```php
$preview = app(\Anikeen\Id\AnikeenId::class)->createOrderPreview([
    'country_iso' => 'DE',
    'items' => [['type' => 'physical', 'name' => 'T-shirt', 'gross_price' => '24.90', 'unit' => 'piece', 'units' => 1]],
])->shift();

$preview->gross_total;
```

### Physical goods and shipments

Physical goods need a shipping address (`first_name`, `last_name`, `address`, `postal_code`,
`city`, `country_iso`). The user can change both addresses on the confirmation page, so read the
shipping address from the order (or the `order.completed` webhook) before you ship. An item's
`image_url` is copied to Anikeen ID and stays visible on the order and invoice:

```php
$order = $user->orders()->create([
    'billing_address_id' => $addressId,
    'shipping_address' => ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'address' => 'Main St', 'house_number' => '1', 'postal_code' => '10115', 'city' => 'Berlin', 'country_iso' => 'DE'],
    'items' => [
        ['type' => 'physical', 'name' => 'T-shirt', 'unit' => 'piece', 'units' => 1, 'gross_price' => '24.90', 'image_url' => 'https://shop.test/img/shirt.png'],
        ['type' => 'physical-reduced', 'name' => 'Artbook', 'unit' => 'piece', 'units' => 1, 'gross_price' => '19.95'],
    ],
]);
```

Once a paid order is sent, create a shipment. Anikeen ID adds its delivery note to the user's
order documents (with the tracking link), mails it to the user (`'notify_customer' => false` if
your shop sends its own mail) and sends `order.shipped` to your webhook endpoints.
Without `items`, everything physical that is left is shipped; partial deliveries name the items:

```php
$order = $user->orders()->find($orderId);

$shipment = $order->shipments()->create([
    'carrier' => 'DHL',
    'tracking_number' => '00340434161234567890',
    'tracking_url' => 'https://www.dhl.de/…?piececode=00340434161234567890',
    // 'items' => [['order_item_id' => $itemId, 'units' => 1]],
]);

$shipment->number;          // LS-2026-0001
$shipment->deliveryNote();  // the delivery note as PDF
```

To know what is left to ship, compare the order's items with its shipments:

```php
$items = $order->orderItems()->toArray();     // id, type, name, units, …
$shipments = $order->shipments()->toArray();  // each with items: [{order_item_id, units}]
```

Only the app that created the order (or a trusted app) can ship it, and only once it is paid.

### Subscriptions

Subscriptions work like orders: create a draft, let the user confirm it, read it back.

```php
$subscription = $user->subscriptions()->create([
    'name' => 'Pixel Stream Pass',
    'description' => 'All streams, every month',
    'unit' => 'month',
    'price' => 6.714286,           // net per unit, 7.99 with 19 % VAT
]);

return redirect()->away($subscription->confirmationUrl(route('pass.return')));
// later: https://shop.test/pass/return?status=completed&subscription=01J…
```

After the return, read the subscription and check its state:

```php
$subscription = $user->subscriptions()->find($id);

if ($subscription->isActive()) {
    // grant access
}
```

`status()` returns one of the `Anikeen\Id\Enums\SubscriptionStatus` constants (the API sends the
same value as `status`):

| `status()` | Meaning | `isActive()` |
|---|---|---|
| `draft` | Created, not confirmed by the user yet | no |
| `trial` | Running, in its trial period | yes |
| `active` | Running and billed | yes |
| `paused` | Runs until the paid period ends, then expires (`resume()` to continue) | yes |
| `cancelling` | Runs until a scheduled cancellation takes effect | yes |
| `expired` | Paused and ran out; `resume()` starts a new period | no |
| `cancelled` | Cancelled for good | no |

There are shortcuts for each: `isDraft()`, `isTrial()`, `isPaused()`, `isCancelling()`,
`isExpired()`, `isCancelled()`. App subscriptions (`appSubscriptions()`) have the same methods.

#### Free trials

Give a subscription a free trial with `trial_days` (1–365, counted from the user's confirmation or
the checkout) or a fixed `trial_ends_at` (ISO 8601, in the future), not both. The subscription
starts right away with `status` `trial` and nothing billed; the first period is billed when the
trial ends. `trial_once` refuses the trial if the user already had one with your app in the same
`group` (422 when creating, 409 `trial_already_used` when checking out):

```php
$subscription = $user->subscriptions()->create([
    'name' => 'Pixel Stream Pass',
    'unit' => 'month',
    'price' => 6.714286,
    'trial_days' => 14,
    'trial_once' => true,
]);
```

A few days before the trial ends, Anikeen ID sends `subscription.trial_will_end`
(`EventType::SUBSCRIPTION_TRIAL_WILL_END`), e.g. to remind the user. Cancelling during the trial
bills nothing. Trials work the same for app subscriptions and per subscription of a checkout
session.

#### Discounts

A `discount` (e.g. a coupon) lowers the price of billed periods: every discounted period gets a
negative line with the `label` next to its usage on the same bill, until the discount ends.

```php
$subscription = $user->subscriptions()->create([
    'name' => 'Pixel Stream Pass',
    'unit' => 'month',
    'price' => 6.714286,
    'discount' => [
        'label' => 'SUMMER20',         // shown to the user, e.g. the coupon code
        'percent_off' => 20,           // or 'amount_off' => 1.50 (net, per period)
        'duration' => 'repeating',     // once, repeating or forever
        'duration_in_periods' => 3,    // only for repeating
    ],
]);

$subscription->discount;               // {label, percent_off, amount_off, duration, duration_in_periods, periods_remaining}
```

A period is one unit of the subscription (a month for monthly prices). A free trial isn't a
billed period: the discount starts with the first paid one. `periods_remaining` counts down with
every billed period (null for `forever`, 0 once it ended); then `subscription.discount_ended`
(`EventType::SUBSCRIPTION_DISCOUNT_ENDED`) is sent. A discount can't be combined with a
`monthly_cap`. It is set when the subscription is created (app subscriptions and checkout
sessions alike) and changed afterwards with
[`change()`](#changing-running-subscriptions-trusted-apps). A resize (`predecessor_id`) doesn't
carry it over: send the successor the rest (`'duration' => 'repeating'`,
`'duration_in_periods' => $old->discount->periods_remaining`).

### Webhooks

Anikeen ID sends `order.*`, `subscription.*` and `checkout.*` events to the webhook endpoints of
your app (see [`EventType`](src/Id/Webhooks/EventType.php) for all of them). Verify each request
with the raw body and answer 2xx within 10 seconds; failed deliveries are retried for about three
days.

Exclude the route from CSRF protection in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['webhooks/*']);
})
```

```php
use Anikeen\Id\Exceptions\SignatureVerificationException;
use Anikeen\Id\Webhooks\EventType;
use Anikeen\Id\Webhooks\Webhook;

Route::post('webhooks/anikeen', function (Request $request) {
    try {
        $event = Webhook::constructEvent($request->getContent(), $request->header('Anikeen-Signature'), config('services.anikeen.webhook_secret'));
    } catch (SignatureVerificationException) {
        abort(400);
    }

    if (! ProcessedEvent::firstOrCreate(['id' => $event['id']])->wasRecentlyCreated) {
        return response()->noContent(); // already handled
    }

    $object = $event['data']['object'];

    match ($event['type']) {
        EventType::ORDER_COMPLETED => Shop::fulfil($object['id'], $object['shipping_address']),
        EventType::ORDER_CANCELLED => Shop::release($object['id']),
        EventType::SUBSCRIPTION_ACTIVATED => Pass::grant($object['id']),
        EventType::SUBSCRIPTION_CANCELLED => Pass::revoke($object['id']),
        EventType::CHECKOUT_COMPLETED => Panel::provision($object['subscription_ids']),
        default => null,
    };

    return response()->noContent();
});
```

`data.object` is the order, subscription or checkout session as the API returns it. Events can
arrive more than once or out of order: use `$event['id']` to skip duplicates, and reload the
object through the API when the current state matters. The admin panel can send a
`webhook.test` event to check your endpoint.

### Errors

Every failed request throws `Anikeen\Id\Exceptions\ApiException`, with the user's token as
well as with the app's (`app*()`). It carries:

- `status`: the HTTP status (also `getCode()`), `0` when Anikeen ID could not be reached,
- `error`: a stable error code when Anikeen ID sent one (e.g. `billing_address_missing`,
  `already_cancelled`), otherwise `null`,
- `getMessage()`: Anikeen ID's reason,
- `isNotFound()` (404), `isConflict()` (409) and `isTemporary()` (no response, 429 or 5xx: retry
  later).

```php
use Anikeen\Id\Exceptions\ApiException;

try {
    $order = $user->orders()->create($attributes);
} catch (ApiException $e) {
    if ($e->isTemporary()) {
        return back()->with('error', 'Anikeen ID is not reachable right now, please try again.');
    }

    report($e);

    return back()->with('error', $e->getMessage());
}
```

Failed lists (`$user->invoices()`, …) throw `CollectionException`, a subclass of `ApiException`.
`ConfirmationRequiredException` (a third-party app called `checkout()`, see above) stays its own
type. All of them extend `ResourceException`, so existing `catch (ResourceException $e)` blocks
keep working.

### Testing

Develop against staging (`ANIKEEN_ID_MODE=staging`) with an OAuth client registered there.
Payments on staging run in Stripe's test mode: pay with Stripe's
[test cards](https://docs.stripe.com/testing), e.g. `4242 4242 4242 4242` with any future expiry
date and any CVC. No money is charged.
Point `ANIKEEN_ID_BASE_URL` at your own Anikeen ID instance if you run one locally.

In unit tests, swap the HTTP client for a Guzzle `MockHandler`:

```php
$mock = new \GuzzleHttp\Handler\MockHandler([
    new \GuzzleHttp\Psr7\Response(201, ['Content-Type' => 'application/json'], json_encode(['data' => ['id' => '01JORDER', 'status' => 'draft']])),
]);

$this->app->instance(AnikeenId::class, (new AnikeenId())->setHttpClient(new \GuzzleHttp\Client([
    'handler' => \GuzzleHttp\HandlerStack::create($mock),
    'base_uri' => 'https://id.test/api/',
])));
```

## Trusted apps

Apps operated by Anikeen can be marked as trusted. They can charge without asking the user and
use their own token (client credentials), so billing keeps working when the user's login expired.

### App subscriptions (trusted apps, no user token)

`subscriptions()` acts with the user's own access token and stops working when the user's login
expired. Trusted apps can manage a user's subscriptions with the app's own token (client
credentials, scope `subscriptions:client`) instead:

```php
$subscriptions = $team->owner->appSubscriptions(); // nothing is requested yet

$subscription = $subscriptions->create([
    'group' => $team->id,
    'name' => 'Cloud Server S',
    'unit' => 'hour',
    'price' => 0.0137,
    'idempotency_key' => "server:{$pendingServer->id}", // retries return the same subscription
]);

$subscription->checkout();             // bill the first period
$subscription->checkout(startsAt: $domain->registered_at); // already running: bill the period that started then
$subscription->pause();                // expires when the paid period ends
$subscription->resume();               // expired subscriptions start a new period
$subscription->revoke(refund: false);  // cancel

$subscriptions->find($id);             // null if unknown or not this user's
$subscriptions->all(['group' => $team->id, 'active' => true]);
```

The user's Anikeen ID is read from the billable's `anikeen_id` attribute
(`AnikeenId::useUserIdField('…')` to change it). The app must be trusted by Anikeen ID; it sees the
subscriptions it created (plus those of clients delegated to it).

Failures throw `Anikeen\Id\Exceptions\ApiException` with `status` (0 = not reachable), a stable
`error` code (`already_active`, `already_cancelled`, `not_active`, `already_paused`,
`already_resumed`, `billing_address_missing`, `not_changeable`, `subscription_not_found`,
`idempotency_key_conflict`, `in_checkout_session`, `trial_already_used`, `proration_not_possible`,
`credit_exceeds_bill`) and `isNotFound()`, `isConflict()`, `isTemporary()`:

```php
try {
    $subscription->revoke();
} catch (ApiException $e) {
    if ($e->isNotFound() || $e->error === 'already_cancelled') {
        // already gone
    } elseif ($e->isTemporary()) {
        // retry later
    } else {
        throw $e;
    }
}
```

### Changing running subscriptions (trusted apps)

`update()` changes a subscription before its checkout (afterwards only the description).
`change()` changes price, unit, name, description or discount of a running subscription, like a
plan change (send only what changes):

```php
use Anikeen\Id\Resources\AppSubscription;

// upgrade now, prorated: the unused rest of the period is credited, the new price charged for it
$subscription->change(['apply' => AppSubscription::APPLY_NOW, 'price' => '0.0274', 'name' => 'Cloud Server M']);

// downgrade from the next period on
$subscription->change(['apply' => AppSubscription::APPLY_PERIOD_END, 'price' => '4.99']);
$subscription->scheduled_change;       // {applies_at, name, description, unit, price, discount, created_at}
$subscription->cancelScheduledChange(); // keep the current values after all
```

- `now` credits the unused part of the current period at the rate it was billed at and charges
  the rest at the new price, both on the user's open bill. A new `unit` starts a new, fully billed
  period now. A downgrade whose credit would exceed the open bill is refused with 409
  `credit_exceeds_bill`: schedule it for the period end instead. 409 `proration_not_possible` when
  the current period wasn't billed to the subscription (e.g. a takeover); `period_end` works.
- `period_end` changes nothing now; the next period is billed at the new values. A new
  `period_end` change replaces the scheduled one, `now` drops it.
- During a free trial nothing was billed: both just change what the trial ends in.
- `discount` replaces the discount from the next billed period on, `null` ends it. When the unit
  changes, a discount that counts in periods or an `amount_off` has to be sent again (422).
- Subscriptions with a monthly cap change only with `period_end`.
- Only running subscriptions can change: 409 `not_changeable` for drafts (use `update()`), paused,
  cancelling, expired and cancelled ones.

When a change takes effect (right away or at the period end), Anikeen ID sends
`subscription.updated` (`EventType::SUBSCRIPTION_UPDATED`) with the old values of the changed
fields in `$event['data']['previous_attributes']`. Telling the user about a price increase (and
getting consent where needed) is up to your app.

### App orders (trusted apps, no user token)

Orders that bill the app's subscriptions, read with the app's token (scope `orders:client`):

```php
$team->owner->appOrders()->pending();         // unpaid orders of one user, oldest first
app(AnikeenId::class)->appOrders()->pending(); // of all users
$team->owner->appOrders()->find($orderId);     // null if unknown or not this user's
```

Each order has `status` (`processing` = current month, still collecting; `pending` = closed and not
paid; `completed`; `cancelled`), the totals, `payment_attempts`, `next_payment_attempt_at` (null =
no more automatic retries) and `last_payment_error`.

### Checkout sessions (the user confirms several subscriptions at once)

Instead of checking subscriptions out itself, an app can let the user confirm them on Anikeen ID,
all of them in one step: a checkout session bundles new subscriptions (or existing drafts of the
app, `['id' => …]`) of one user. The user sees every item with its price and the total of the first
period, confirms (all subscriptions start, or none) or cancels, and returns to `return_url`.
Open sessions expire after 24 hours with their drafts. Trusted apps can still check out directly;
sessions are opt-in.

Example: a cart with several products and coupon prices:

```php
use Anikeen\Id\Exceptions\ApiException;

$session = $request->user()->appCheckoutSessions()->create([   // app token; or ->checkoutSessions() with the user's token
    'return_url' => route('builder.done'),                      // same origin as one of your redirect URIs
    'metadata' => ['cart' => $cart->id],                        // returned as is
    'idempotency_key' => "cart:{$cart->id}",                    // retries return the same session
    'subscriptions' => $cart->items->map(fn ($item) => [
        'group' => $team->id,
        'name' => $item->product->name,
        'description' => $item->label,
        'unit' => $item->unit,                                  // hour, day, week, month, year
        'price' => $item->priceAfterCoupon(),                   // net, per unit
        'payload' => ['cart_item' => $item->id],
    ])->all(),
]);

$cart->update(['checkout_session_id' => $session->id, 'subscription_ids' => $session->subscriptionIds()]);

return redirect($session->confirmationUrl());
// later: https://console.test/builder/done?status=completed&checkout_session=01K…
```

Provision when the webhook arrives (or after checking the session through the API; never trust the
query string):

```php
use Anikeen\Id\Webhooks\EventType;

match ($event['type']) {
    EventType::CHECKOUT_COMPLETED => Provisioner::provision($event['data']['object']['subscriptions']), // all running
    EventType::CHECKOUT_CANCELLED, EventType::CHECKOUT_EXPIRED => Cart::release($event['data']['object']['metadata']['cart'] ?? null),
    default => null,
};

app(AnikeenId::class)->appCheckoutSessions()->find($id)?->isCompleted(); // any user's session
$session->refresh()->subscriptions();  // AppSubscription objects
$session->cancel();                    // withdraw an open session and its drafts (e.g. the cart changed)
```

While a session is open, its subscriptions can't be checked out or repriced by the app (409
`in_checkout_session` / `not_changeable`). The session's `status` is `open`, `completed`,
`cancelled` or `expired` (`isOpen()`, `isCompleted()`, `isCancelled()` for cancelled or expired).
`subscription.activated` / `subscription.cancelled` are still sent per subscription, before the
`checkout.*` event of the session.


## API authentication

Use this when your own API accepts access tokens issued by Anikeen ID.

### Registering Middleware

To call your API from your own frontend with the user's session, append the middleware to the
web middleware group in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \Anikeen\Id\Http\Middleware\CreateFreshApiToken::class,
    ]);
})
```

### Implementing Auth (API tokens)

The package registers an `anikeen` guard driver and an `anikeen` user provider. Reference them
in `config/auth.php`:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'anikeen',
        'provider' => 'anikeen',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],

    'anikeen' => [
        'driver' => 'anikeen',
        'model' => App\Models\User::class,
        'column' => 'anikeen_id', // optional, see below
    ],
],
```

The guard accepts `Authorization: Bearer <access token>` with an access token issued by Anikeen ID
(personal access token or OAuth token, a JWT). It verifies the RS256 signature with Anikeen ID's
public key and the time claims (`exp`, `nbf`, `iat`, 60 seconds leeway). The token's `sub` (the
user's Anikeen ID) is looked up in the column `anikeen_id` of the user model: the provider's
`column`, else `services.anikeen.user_id_column`, else `AnikeenId::useUserIdField()`. Tokens of
unknown users, client credentials tokens (no `sub`), expired or forged tokens leave the request
unauthenticated, so `auth:api` answers `401 {"message": "Unauthenticated."}` to JSON requests.

The user model uses `Anikeen\Id\HasAnikeenTokens`; the token's scopes are then available:

```php
$request->user()->anikeenTokenCan('user');               // has the scope (or `*`)
$request->user()->anikeenTokenCanAny(['user', 'user:read']);
```

or as middleware (`403` without the scope):

```php
use Anikeen\Id\Http\Middleware\CheckForAnyScope;
use Anikeen\Id\Http\Middleware\CheckScopes;

Route::middleware(['auth:api', CheckScopes::using('user')])->post(...);                    // all scopes
Route::middleware(['auth:api', CheckForAnyScope::using('user', 'user:read')])->get(...);  // any scope
```

Options in `config/services.php` (`anikeen`):

| Key | Default | |
|-----|---------|-|
| `public_key` | – | Anikeen ID's public key (PEM; `\n` escapes allowed) |
| `public_key_path` | – | path to that key; without both the key shipped for the `mode` is used |
| `user_id_column` | `anikeen_id` | column holding the user's Anikeen ID |
| `verify_revocation` | `false` | also ask Anikeen ID (`GET v1/user`) whether the token is still valid, so revoked tokens stop working before they expire; fails closed when Anikeen ID is unreachable |
| `revocation_cache_ttl` | `300` | seconds that answer is cached per token |

The key is read once per process. In tests `AnikeenId::actingAs($user, ['user'])` signs in without a token.

## General

#### Setters and Getters

```php
$anikeenId = new Anikeen\Id\AnikeenId();

$anikeenId->setClientId('abc123');
$anikeenId->setClientSecret('abc456');
$anikeenId->setToken('abcdef123456');

$anikeenId = $anikeenId->withClientId('abc123');
$anikeenId = $anikeenId->withClientSecret('abc123');
$anikeenId = $anikeenId->withToken('abcdef123456');
```

#### Results and resources

Low-level methods on `AnikeenId` (e.g. `getAuthedUser()`, `createOrderPreview()`,
`refreshToken()`) return a `Result`. Check it before you use it:

```php
$result = $anikeenId->getAuthedUser();

if (!$result->success()) {
    die('Ooops: ' . $result->error());
}

$user = $result->shift(); // the object, or the first item of a list
```

Resource methods (`$user->orders()`, `$anikeenId->sshKeysByUserId()`, …) return objects and throw
`ApiException` instead (see [Errors](#errors)).

## Examples

#### Get User SSH Keys

Uses the app's own token (client credentials):

```php
$sshKeys = app(Anikeen\Id\AnikeenId::class)->sshKeysByUserId($anikeenUserId);

foreach ($sshKeys->toArray() as $sshKey) {
    echo $sshKey->name;
}
```

#### OAuth Tokens

```php
$anikeenId = new Anikeen\Id\AnikeenId();

$anikeenId->setToken('abcdef123456');

$user = $anikeenId->getAuthedUser()->shift();
```

```php
$user = $anikeenId->withToken('uvwxyz456789')->getAuthedUser()->shift();
```

#### Facade

```php
use Anikeen\Id\Facades\AnikeenId;

AnikeenId::withToken('abcdef123456')->getAuthedUser();
```

## Documentation

## AnikeenId

### Oauth

```php
public function retrievingToken(string $grantType, array $attributes): Result
```

### ManagesPricing

```php
public function createOrderPreview(array $attributes = []): Result
```

### ManagesProducts

```php
public function products(): Result
public function boxes(): Result
```

### ManagesShippingLabels

```php
public function shippingLabels(array $parameters = []): Result
public function shippingLabel(string $id): Result
public function shippingLabelPdf(string $id): ?string
public function issueShippingLabel(array $attributes): Result
public function shipShippingLabel(array $hex): Result
public function shippingPriceList(): Result
```

### ManagesUserBilling

```php
public function appOrders(): AppOrders
public function appCheckoutSessions(): AppCheckoutSessions
public function createOrderForUser(string $userId, array $attributes): Result
public function createTransactionForUser(string $userId, array $attributes): Result
```

### ManagesSocialiteUsers

```php
public function socialiteUsers(): SocialiteUsers
```

### ManagesSshKeys

```php
public function sshKeysByUserId(string $userId): SshKeys
```

### ManagesUsers

```php
public function getAuthedUser(): Result
public function createUser(array $attributes): Result
public function findOrCreateSocialiteUser(string $provider, string $providerId, ?string $email = null, ?string $name = null): Result
public function refreshToken(string $storedRefreshToken, string $scope = ''): Result
public function isEmailExisting(string $email): Result
public function setParent(mixed $parent): self
public function getParent()
```


## Billable

### ManagesAddresses

```php
public function addresses(): Addresses
public function hasDefaultBillingAddress(): bool
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesBalance

```php
public function balance(): float
public function charges(): float
public function billingOverview(): object
public function charge(string|float $amount, string $paymentMethodId, array $options = []): Transaction
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesCountries

```php
public function countries(): Countries
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesInvoices

```php
public function invoices(array $parameters = []): Invoices
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesOrders

```php
public function orders(array $parameters = []): Orders
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesPaymentMethods

```php
public function paymentMethods(): PaymentMethods
public function hasPaymentMethod(): ?PaymentMethod
public function defaultPaymentMethod(): ?PaymentMethod
public function hasDefaultPaymentMethod(): bool
public function setDefaultPaymentMethod(string $id): PaymentMethod
public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
public function createSetupIntent(array $options = []): Result
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesProfile

```php
public function profilePortalUrl(?string $returnUrl = null, array $options = []): string
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesSubscriptions

```php
public function subscriptions(): Subscriptions
public function appSubscriptions(): AppSubscriptions
public function appCheckoutSessions(): AppCheckoutSessions
public function checkoutSessions(): CheckoutSessions
public function appOrders(): AppOrders
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesTaxation

```php
public function vatRate(): float
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesTransactions

```php
public function transactions(): Transactions
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```

### ManagesUserSshKeys

```php
public function sshKeys(): SshKeys
public function setBillable(Billable|Model $billable): self
public function getBillable(): Billable
```


[**OAuth Scopes Enums**](https://github.com/anikeen-com/id/blob/main/src/Id/Enums/Scope.php)

## Development

#### Run Tests

```shell
composer test
```

#### Generate Documentation

```shell
composer docs
```
