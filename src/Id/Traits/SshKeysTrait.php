<?php


namespace Anikeen\Id\Traits;

use Anikeen\Id\ApiOperations\Delete;
use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Result;

trait SshKeysTrait
{

    use Get, Post, Delete;

    /**
     * Get currently authed user with Bearer Token
     */
    public function getSshKeysByUserId(int $id): Result
    {
        return $this->get("v1/users/$id/ssh-keys/json", [], null);
    }

    /**
     * Creates ssh key for the currently authed user
     */
    public function createSshKey(string $publicKey, string $name = null): Result
    {
        return $this->post('v1/ssh-keys', [
            'public_key' => $publicKey,
            'name' => $name,
        ]);
    }

    /**
     * Deletes a given ssh key for the currently authed user
     */
    public function deleteSshKey(int $id): Result
    {
        return $this->delete("v1/ssh-keys/$id", []);
    }
}