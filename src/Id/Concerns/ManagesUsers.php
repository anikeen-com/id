<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Result;
use Anikeen\Id\Support\AppRequest;
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
     * Finds the account of an email address or creates it (trusted apps, app token). The email
     * address is matched regardless of case; a new account stores it in lower case, unverified.
     * An existing account is returned unchanged (only a missing name is filled in).
     *
     * @param array{
     *       name?: null|string,
     *       first_name?: null|string,
     *       last_name?: null|string,
     *       username?: null|string,
     *       email: string,
     *       password?: null|string,
     *       password_confirmation?: null|string,
     *       send_invitation?: bool
     *   } $attributes The user data
     *     - name:                  The display name (optional)
     *     - first_name:            The first name (optional, joined with last_name into name)
     *     - last_name:             The last name (optional, joined with first_name into name)
     *     - username:              The username (optional)
     *     - email:                 The email (required)
     *     - password:              The password (optional, can be reset by the user if not provided)
     *     - password_confirmation: Defaults to password
     *     - send_invitation:       Send the user an invitation mail with a link to set a password
     *                              (optional, only when this call created the account without a
     *                              password, never for an existing account)
     * @return Result `data` is the user plus `created` (this call created the account) and
     *     `invitation_sent` (the invitation mail was sent); 201 when created, 200 when it existed
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
     * Looks up the account of an email address without creating or changing anything (trusted
     * apps, app token). The email address is matched regardless of case.
     *
     * `data` is `object{id: string, name: ?string, email: string, email_verified: bool,
     * has_billing_address: bool}`.
     *
     * @return Result|null null when no account uses the email address
     * @throws ApiException when the request fails for another reason
     */
    public function findUserByEmail(string $email): ?Result
    {
        try {
            return AppRequest::send('POST', 'v1/admin/users/lookup', ['email' => $email]);
        } catch (ApiException $e) {
            // only Anikeen ID's own answer means "no account", not e.g. an unknown route
            if ($e->isNotFound() && $e->error === 'user_not_found') {
                return null;
            }

            throw $e;
        }
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
