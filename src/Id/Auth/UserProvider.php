<?php

namespace Anikeen\Id\Auth;

use Anikeen\Id\AnikeenId;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as Base;

class UserProvider implements Base
{
    /**
     * Create a new AnikeenId user provider.
     */
    public function __construct(protected Base $provider, protected string $providerName, protected ?string $column = null)
    {
        //
    }

    /**
     * The column that holds a user's Anikeen ID: the provider's `column`, else
     * `services.anikeen.user_id_column`, else AnikeenId::getUserIdField() (`anikeen_id`).
     */
    public function column(): string
    {
        return $this->column ?: (config('services.anikeen.user_id_column') ?: AnikeenId::getUserIdField());
    }

    /**
     * Find the user of an Anikeen ID (the `sub` of an access token).
     */
    public function retrieveByAnikeenId(string $anikeenId): ?Authenticatable
    {
        if (method_exists($this->provider, 'retrieveByAnikeenId')) {
            return $this->provider->retrieveByAnikeenId($anikeenId);
        }

        // eloquent / database providers: a plain where on the column
        return $this->provider->retrieveByCredentials([$this->column() => $anikeenId]);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->provider->retrieveById($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->provider->retrieveByToken($identifier, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->provider->updateRememberToken($user, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return $this->provider->retrieveByCredentials($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Get the name of the user provider.
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        $this->provider->rehashPasswordIfRequired($user, $credentials, $force);
    }
}
