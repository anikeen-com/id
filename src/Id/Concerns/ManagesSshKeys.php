<?php


namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\Resources\SshKeys;
use Throwable;

trait ManagesSshKeys
{
    use Get;

    /**
     * SSH keys of a user, read with the app's own token (trusted apps, scope ssh-keys:read),
     * e.g. to install them on a new server.
     *
     * @throws Throwable
     */
    public function sshKeysByUserId(string $userId): SshKeys
    {
        $app = $this->asApp();

        return SshKeys::builder(fn() => $app->get(sprintf('v1/client/users/%s/ssh-keys', $userId)))
            ->setParent($app);
    }
}