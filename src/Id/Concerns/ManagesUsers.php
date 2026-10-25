<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Result;
use Throwable;

trait ManagesUsers
{
    use Get, Post;
    use HasParent;

    /**
     * Get currently authed user with Bearer Token
     *
     * @throws Throwable
     */
    public function getAuthedUser(): Result
    {
        return $this->get('v1/user');
    }

    /**
     * Creates a new user on behalf of the current user.
     *
     * @param array{
     *       first_name: null|string,
     *       last_name: null|string,
     *       username: null|string,
     *       email: string,
     *       password: null|string
     *   } $attributes The user data
     *     - first_name:  The first name (optional)
     *     - last_name:   The last name (optional)
     *     - username:    The username (optional)
     *     - email:       The email (required)
     *     - password:    The password (optional, can be reset by the user if not provided)
     * @throws Throwable
     */
    public function createUser(array $attributes): Result
    {
        // the API takes a single display name
        if (!isset($attributes['name']) && (isset($attributes['first_name']) || isset($attributes['last_name']))) {
            $attributes['name'] = trim(($attributes['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? '')) ?: null;
        }

        if (isset($attributes['password']) && !isset($attributes['password_confirmation'])) {
            $attributes['password_confirmation'] = $attributes['password'];
        }

        unset($attributes['first_name'], $attributes['last_name']);

        // finds the user by email or creates it (trusted apps, app token)
        return $this->asApp()->post('v1/admin/users', $attributes);
    }

    /**
     * Find the user of a social login or create it (trusted apps, app token).
     *
     * @param string $provider The socialite driver, e.g. "twitch".
     * @param string $providerId The user's ID at the provider.
     * @throws Throwable
     */
    public function findOrCreateSocialiteUser(string $provider, string $providerId, ?string $email = null, ?string $name = null): Result
    {
        return $this->asApp()->post('v1/admin/users/socialite', array_filter([
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $email,
            'name' => $name,
        ], fn ($value) => $value !== null));
    }

    /**
     * Refreshes the access token using the refresh token.
     */
    public function refreshToken(string $storedRefreshToken, string $scope = ''): Result
    {
        return $this->post('../oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $storedRefreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $scope,
        ]);
    }

    /**
     * Checks if the given email exists.
     *
     * @param string $email The email to check.
     * @throws Throwable
     */
    public function isEmailExisting(string $email): Result
    {
        // `data.exists` tells whether an account uses the email (trusted apps, app token)
        return $this->asApp()->post('v1/admin/users/check', [
            'email' => $email,
        ]);
    }
}
