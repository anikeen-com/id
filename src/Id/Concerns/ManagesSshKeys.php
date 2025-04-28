<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Delete;
use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\ApiOperations\Post;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesSshKeys
{
    use Get, Post, Delete;

    /**
     * Get currently authed user with Bearer Token.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function sshKeysByUserId(string $sskKeyId): Result
    {
        return $this->get(sprintf('v1/users/%s/ssh-keys/json', $sskKeyId));
    }

    /**
     * Creates ssh key for the currently authed user.
     *
     * @param string $publicKey The public key to be added
     * @param string|null $name The name of the key (optional)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function createSshKey(string $publicKey, string $name = null): Result
    {
        return $this->post('v1/ssh-keys', [
            'public_key' => $publicKey,
            'name' => $name,
        ]);
    }

    /**
     * Deletes a given ssh key for the currently authed user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function deleteSshKey(int $sshKeyId): Result
    {
        return $this->delete(sprintf('v1/ssh-keys/%s', $sshKeyId));
    }
}