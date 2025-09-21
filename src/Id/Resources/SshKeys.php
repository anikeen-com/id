<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasParent;
use Throwable;

class SshKeys extends BaseCollection
{
    use HasParent;

    /**
     * Creates ssh key for the currently authed user.
     *
     * @param string $publicKey The public key to be added
     * @param string|null $name The name of the key (optional)
     * @throws Throwable
     */
    public function create(string $publicKey, ?string $name = null): SshKey
    {
        return (new SshKey(fn() => $this->parent->post('v1/ssh-keys', [
            'public_key' => $publicKey,
            'name' => $name,
        ])))->setParent($this->parent);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?SshKey
    {
        return (new SshKey(fn() => $this->parent->get(sprintf('v1/ssh-keys/%s', $id))));
    }
}