<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Resources\SshKeys;
use Throwable;

trait ManagesSshKeys
{
    use Get;

    /**
     * Get currently authed user with Bearer Token.
     *
     * @throws Throwable
     */
    public function sshKeysByUserId(string $sskKeyId): SshKeys
    {
        if (!isset($this->sshKeysCache)) {
            $this->sshKeysCache = SshKeys::builder(fn() => $this->get(sprintf('v1/users/%s/ssh-keys/json', $sskKeyId)))
                ->setParent($this);
        }

        return $this->sshKeysCache;
    }
}