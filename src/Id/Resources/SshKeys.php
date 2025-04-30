<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasParent;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class SshKeys extends BaseCollection
{
    use HasParent;

    /**
     * Creates ssh key for the currently authed user.
     *
     * @param string $publicKey The public key to be added
     * @param string|null $name The name of the key (optional)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function create(string $publicKey, ?string $name = null): SshKey
    {
        return (new SshKey($this->parent->post('v1/ssh-keys', [
            'public_key' => $publicKey,
            'name' => $name,
        ])))->setParent($this->parent);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?SshKey
    {
        /** @var Result $result */
        $result = $this->parent->get(sprintf('v1/ssh-keys/%s', $id));

        return $result->success()
            ? (new SshKey($result))
                ->setParent($this->parent)
            : null;
    }
}