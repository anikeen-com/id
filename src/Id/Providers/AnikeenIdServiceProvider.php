<?php

namespace Anikeen\Id\Providers;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Auth\TokenGuard;
use Anikeen\Id\Auth\UserProvider;
use Anikeen\Id\Contracts;
use Anikeen\Id\Helpers\JwtParser;
use Anikeen\Id\Repository;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Support\ServiceProvider;

class AnikeenIdServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->singleton(Contracts\AppTokenRepository::class, Repository\AppTokenRepository::class);
        $this->app->singleton(AnikeenId::class, function () {
            return new AnikeenId;
        });

        $this->registerGuard();
    }

    /**
     * Register the `anikeen` guard driver and user provider.
     *
     * Laravel binds non-static extend() closures to the AuthManager, so these closures are
     * static and use nothing but their arguments.
     */
    protected function registerGuard(): void
    {
        $this->callAfterResolving('auth', static function (AuthManager $auth): void {
            $auth->provider('anikeen', static fn (Application $app, array $config) => new AnikeenIdUserProvider(
                $app->make(AnikeenId::class),
                $app->make('request'),
                $config['model'] ?? $app['config']->get('auth.providers.users.model'),
                $config['fields'] ?? [],
                $config['column'] ?? null,
            ));

            $auth->extend('anikeen', static function (Application $app, string $name, array $config): RequestGuard {
                $guard = AnikeenIdServiceProvider::makeGuard($app, $config);

                $app->refresh('request', $guard, 'setRequest');

                return $guard;
            });
        });
    }

    /**
     * Make an instance of the token guard.
     */
    public static function makeGuard(Application $app, array $config): RequestGuard
    {
        $providerName = $config['provider'] ?? $app['config']->get('auth.defaults.provider', 'users');
        $provider = $app['auth']->createUserProvider($providerName)
            ?? throw new InvalidArgumentException("Authentication user provider [$providerName] is not configured.");
        $providerConfig = $app['config']->get("auth.providers.$providerName", []);

        return new RequestGuard(static function (Request $request) use ($app, $provider, $providerName, $providerConfig, $config) {
            return (new TokenGuard(
                new UserProvider($provider, $providerName, $config['column'] ?? $providerConfig['column'] ?? null),
                $app->make('encrypter'),
                $app->make(JwtParser::class)
            ))->user($request);
        }, $app['request'], $provider);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            AnikeenId::class,
        ];
    }
}
