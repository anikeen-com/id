<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\SshKeys;
use Throwable;

trait ManagesUserSshKeys
{
    use HasBillable;

    /**
     * SSH keys of the current user (user token).
     *
     * @throws Throwable
     */
    public function sshKeys(): SshKeys
    {
        $anikeenId = $this->anikeenId();

        return SshKeys::builder(fn() => $anikeenId->get('v1/ssh-keys'))->setParent($anikeenId);
    }
}
