<?php

namespace Anikeen\Id\Providers;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Auth\TokenGuard;
use Anikeen\Id\Auth\UserProvider;
use Anikeen\Id\Contracts;
use Anikeen\Id\Helpers\JwtParser;
use Anikeen\Id\Repository;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
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
        $this->publishes([
            dirname(__DIR__, 3) . '/config/anikeen-id.php' => config_path('anikeen-id.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/config/anikeen-id.php', 'anikeen-id');
        $this->app->singleton(Contracts\AppTokenRepository::class, Repository\AppTokenRepository::class);
        $this->app->singleton(AnikeenId::class, function () {
            return new AnikeenId;
        });

        $this->registerGuard();
    }

    /**
     * Register the token guard.
     */
    protected function registerGuard(): void
    {
        Auth::resolved(function ($auth) {
            $auth->extend('anikeen-id', function ($app, $name, array $config) {
                return tap($this->makeGuard($config), function ($guard) {
                    $this->app->refresh('request', $guard, 'setRequest');
                });
            });
        });
    }

    /**
     * Make an instance of the token guard.
     */
    protected function makeGuard(array $config): RequestGuard
    {
        return new RequestGuard(function ($request) use ($config) {
            return (new TokenGuard(
                new UserProvider(Auth::createUserProvider($config['provider']), $config['provider']),
                $this->app->make('encrypter'),
                $this->app->make(JwtParser::class)
            ))->user($request);
        }, $this->app['request']);
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
