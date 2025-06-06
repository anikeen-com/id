# Anikeen ID

[![Latest Stable Version](https://img.shields.io/packagist/v/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)
[![Total Downloads](https://img.shields.io/packagist/dt/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)
[![License](https://img.shields.io/packagist/l/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)

PHP Anikeen ID API Client for Laravel 11+

## Table of contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [General](#general)
4. [Examples](#examples)
5. [Documentation](#documentation)
6. [Development](#Development)

## Installation

```
composer require anikeen/id
```

## Configuration

Add environmental variables to your `.env` file:

```
ANIKEEN_ID_KEY=
ANIKEEN_ID_SECRET=
ANIKEEN_ID_CALLBACK_URL=http://localhost/auth/callback
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
],
```

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

### Registering Middleware

Append it to the global middleware stack in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \Anikeen\Id\Http\Middleware\CreateFreshApiToken::class,
    ]);
})
```

### Implementing Billable

To implement the `Billable` trait, you need to add the `Billable` trait to your user model.

```php
use Anikeen\Id\Billable;

class User extends Authenticatable
{
    use Billable;

    // Your model code...
}
```

then, you can use the `Billable` trait methods in your user model.

### Change the default access token / refresh token field name

If you access / refresh token fields differs from the default `anikeen_id_access_token` / `anikeen_id_refresh_token`, you can specify the field name in the `AppServiceProvider` boot method:

```php
use Anikeen\Id\AnikeenId;

public function boot(): void
{
    AnikeenId::useAccessTokenField('anikeen_id_access_token');
    AnikeenId::useRefreshTokenField('anikeen_id_refresh_token');
}
```

### Implementing Auth

This method should typically be called in the `boot` method of your `AppServiceProvider` class:

```php
use Anikeen\Id\AnikeenId;
use Anikeen\Id\Providers\AnikeenIdUserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

public function boot(): void
{
    Auth::provider('anikeen', function ($app, array $config) {
        return new AnikeenIdUserProvider(
            $app->make(AnikeenId::class),
            $app->make(Request::class),
            $config['model'],
            $config['fields'] ?? [],
        );
    });
}
```

reference the guard in the `guards` configuration of your `auth.php` configuration file:

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
```

reference the provider in the `providers` configuration of your `auth.php` configuration file:

```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],

    'anikeen' => [
        'driver' => 'anikeen',
        'model' => App\Models\User::class,
        'fields' => ['first_name', 'last_name', 'email'],
    ],
],
```

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

#### Error handling for an unsuccessful query:

```php
$result = $anikeenId->sshKeysByUserId('someInvalidId');

// Check, if the query was successfully
if (!$result->success()) {
    die('Ooops: ' . $result->error());
}
```

#### Shift result to get single key data:

```php
$result = $anikeenId->sshKeysByUserId('someValidId');

$sshKey = $result->shift();

echo $sshKey->name;
```

## Examples

#### Get User SSH Key

```php
$anikeenId = new Anikeen\IdAnikeenId();

$anikeenId->setClientId('abc123');

// Get SSH Key by User ID
$result = $anikeenId->sshKeysByUserId('someValidId');

// Check, if the query was successfully
if (!$result->success()) {
    die('Ooops: ' . $result->error());
}

// Shift result to get single key data
$sshKey = $result->shift();

echo $sshKey->name;
```

#### Create Order Preview

```php
$anikeenId = new \Anikeen\Id\AnikeenId();

// Create new Order Preview
$result = $anikeenId->createOrderPreview([
    'country_iso' => 'de',
    'items' => [
        [
            'type' => 'physical',
            'name' => 'Test',
            'price' => 2.99,
            'unit' => 'onetime',
            'units' => 1,
        ]
    ]
])->shift();

echo $preview->gross_total;
```

#### OAuth Tokens

```php
$anikeenId = new Anikeen\Id\AnikeenId();

$anikeenId->setClientId('abc123');
$anikeenId->setToken('abcdef123456');

$result = $anikeenId->getAuthedUser();

$user = $userResult->shift();
```

```php
$anikeenId->setToken('uvwxyz456789');

$result = $anikeenId->getAuthedUser();
```

```php
$result = $anikeenId->withToken('uvwxyz456789')->getAuthedUser();
```

#### Facade

```php
use Anikeen\Id\Facades\AnikeenId;

AnikeenId::withClientId('abc123')->withToken('abcdef123456')->getAuthedUser();
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

### ManagesSshKeys

```php
public function sshKeysByUserId(string $sskKeyId): SshKeys
```

### ManagesUsers

```php
public function getAuthedUser(): Result
public function createUser(array $attributes): Result
public function isEmailExisting(string $email): Result
public function setParent(?mixed $parent): self
public function getParent()
```


## Billable

### ManagesAddresses

```php
public function addresses(): Addresses
public function hasDefaultBillingAddress(): bool
```

### ManagesBalance

```php
public function balance(): float
public function charges(): float
public function charge(float $amount, string $paymentMethodId, array $options = []): Transaction
```

### ManagesCountries

```php
public function countries(): Countries
```

### ManagesInvoices

```php
public function invoices(array $parameters = []): Invoices
```

### ManagesOrders

```php
public function orders(array $parameters = []): Orders
```

### ManagesPaymentMethods

```php
public function paymentMethods(): PaymentMethods
public function hasPaymentMethod(): ?PaymentMethod
public function defaultPaymentMethod(): ?PaymentMethod
public function hasDefaultPaymentMethod(): bool
public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
public function createSetupIntent(array $options = []): Result
```

### ManagesProfile

```php
public function profilePortalUrl(?string $returnUrl = null, array $options = []): string
```

### ManagesSubscriptions

```php
public function subscriptions(): Subscriptions
```

### ManagesTaxation

```php
public function vat(): float
```

### ManagesTransactions

```php
public function transactions(): Transactions
```


[**OAuth Scopes Enums**](https://github.com/anikeen-com/id/blob/main/src/Enums/Scope.php)

## Development

#### Run Tests

```shell
composer test
```

```shell
BASE_URL=xxxx CLIENT_ID=xxxx CLIENT_KEY=yyyy CLIENT_ACCESS_TOKEN=zzzz composer test
```

#### Generate Documentation

```shell
composer docs
```
