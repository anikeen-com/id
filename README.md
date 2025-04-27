# Anikeen ID

[![Latest Stable Version](https://img.shields.io/packagist/v/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)
[![Total Downloads](https://img.shields.io/packagist/dt/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)
[![License](https://img.shields.io/packagist/l/anikeen/id.svg?style=flat-square)](https://packagist.org/packages/anikeen/id)

PHP Anikeen ID API Client for Laravel 10+

## Table of contents

1. [Installation](#installation)
2. [Event Listener](#event-listener)
3. [Configuration](#configuration)
4. [Examples](#examples)
5. [Documentation](#documentation)
6. [Development](#Development)

## Installation

```
composer require anikeen/id
```

## Event Listener

- Add `SocialiteProviders\Manager\SocialiteWasCalled` event to your `listen[]` array in `app/Providers/EventServiceProvider`.
- Add your listeners (i.e. the ones from the providers) to the `SocialiteProviders\Manager\SocialiteWasCalled[]` that you just created.
- The listener that you add for this provider is `'Anikeen\\Id\\Socialite\\AnikeenIdExtendSocialite@handle',`.
- Note: You do not need to add anything for the built-in socialite providers unless you override them with your own providers.

```
/**
 * The event handler mappings for the application.
 *
 * @var array
 */
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        // add your listeners (aka providers) here
        'Anikeen\\Id\\Socialite\\AnikeenIdExtendSocialite@handle',
    ],
];
```

## Configuration

Copy configuration to config folder:

```
$ php artisan vendor:publish --provider="Anikeen\Id\Providers\AnikeenIdServiceProvider"
```

Add environmental variables to your `.env`

```
ANIKEEN_ID_KEY=
ANIKEEN_ID_SECRET=
ANIKEEN_ID_REDIRECT_URI=http://localhost
```

You will need to add an entry to the services configuration file so that after config files are cached for usage in production environment (Laravel command `artisan config:cache`) all config is still available.

**Add to `config/services.php`:**

```php
'anikeen-id' => [
    'client_id' => env('ANIKEEN_ID_KEY'),
    'client_secret' => env('ANIKEEN_ID_SECRET'),
    'redirect' => env('ANIKEEN_ID_REDIRECT_URI')
],
```

## Implementing Auth

This method should typically be called in the `boot` method of your `AuthServiceProvider` class:

```php
use Anikeen\Id\AnikeenId;
use Anikeen\Id\Providers\AnikeenIdSsoUserProvider;
use Illuminate\Http\Request;

/**
 * Register any authentication / authorization services.
 *
 * @return void
 */
public function boot()
{
    Auth::provider('sso-users', function ($app, array $config) {
        return new AnikeenIdSsoUserProvider(
            $app->make(AnikeenId::class),
            $app->make(Request::class),
            $config['model'],
            $config['fields'] ?? [],
            $config['access_token_field'] ?? null
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
        'driver' => 'anikeen-id',
        'provider' => 'sso-users',
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

    'sso-users' => [
        'driver' => 'sso-users',
        'model' => App\Models\User::class,
        'fields' => ['first_name', 'last_name', 'email'],
        'access_token_field' => 'sso_access_token',
    ],
],
```

## Examples

#### Basic

```php
$anikeenId = new Anikeen\IdAnikeenId();

$anikeenId->setClientId('abc123');

// Get SSH Key by User ID
$result = $anikeenId->getSshKeysByUserId(38);

// Check, if the query was successfull
if ( ! $result->success()) {
    die('Ooops: ' . $result->error());
}

// Shift result to get single key data
$sshKey = $result->shift();

echo $sshKey->name;
```

#### Setters

```php
$anikeenId = new Anikeen\Id\AnikeenId();

$anikeenId->setClientId('abc123');
$anikeenId->setClientSecret('abc456');
$anikeenId->setToken('abcdef123456');

$anikeenId = $anikeenId->withClientId('abc123');
$anikeenId = $anikeenId->withClientSecret('abc123');
$anikeenId = $anikeenId->withToken('abcdef123456');
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

### Oauth

```php
public function retrievingToken(string $grantType, array $attributes)
```

### SshKeys

```php
public function getSshKeysByUserId(int $id)
public function createSshKey(string $publicKey, string $name = NULL)
public function deleteSshKey(int $id)
```

### Users

```php
public function getAuthedUser()
public function createUser(array $parameters)
public function isEmailExisting(string $email)
```

### Delete

```php

```

### Get

```php

```

### Post

```php

```

### Put

```php

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
