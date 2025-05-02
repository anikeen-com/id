<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
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
        return $this->post('v1/users', $attributes);
    }

    /**
     * Refreshes the access token using the refresh token.
     */
    public function refreshToken(string $storedRefreshToken, string $scope = ''): Result
    {
        return $this->post('../oauth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $storedRefreshToken,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $scope,
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
        return $this->post('v1/users/check', [
            'email' => $email,
        ]);
    }
}
