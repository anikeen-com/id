<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\SshKeys;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesSshKeys
{
    use Get;

    /**
     * Get currently authed user with Bearer Token.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function sshKeysByUserId(string $sskKeyId): SshKeys
    {
        return (new SshKeys($this->get(sprintf('v1/users/%s/ssh-keys/json', $sskKeyId))))
            ->setParent($this);
    }
}