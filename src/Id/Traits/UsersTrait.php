<?php


namespace Anikeen\Id\Traits;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\Result;

trait UsersTrait
{

    use Get;

    /**
     * Get currently authed user with Bearer Token
     */
    public function getAuthedUser(): Result
    {
        return $this->get('v1/user');
    }

    /**
     * Creates a new user on behalf of the current user.
     */
    public function createUser(array $parameters): Result
    {
        return $this->post('v1/users', $parameters);
    }

    /**
     * Checks if the given email exists.
     */
    public function isEmailExisting(string $email): Result
    {
        return $this->post('v1/users/check', [
            'email' => $email,
        ]);
    }
}
