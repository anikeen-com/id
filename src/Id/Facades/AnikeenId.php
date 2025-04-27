<?php

namespace Anikeen\Id\Facades;

use Anikeen\Id\AnikeenId as AnikeenIdService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string|static cookie(string $cookie = null)
 * @method static Authenticatable actingAs($user, $scopes = [], $guard = 'api')
 * @method static static withClientId(string $clientId): self
 * @method static string getClientSecret(): string
 */
class AnikeenId extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AnikeenIdService::class;
    }
}