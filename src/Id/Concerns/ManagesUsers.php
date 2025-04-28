<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesUsers
{
    use Get, Post;

    /**
     * Get currently authed user with Bearer Token
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
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
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function createUser(array $attributes): Result
    {
        return $this->post('v1/users', $attributes);
    }

    /**
     * Checks if the given email exists.
     *
     * @param string $email The email to check.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function isEmailExisting(string $email): Result
    {
        return $this->post('v1/users/check', [
            'email' => $email,
        ]);
    }
}
