<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasParent;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @property string $id
 */
class SshKey extends BaseResource
{
    use HasParent;

    /**
     * Deletes a given ssh key for the currently authed user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function delete(): bool
    {
        return $this->parent->delete(sprintf('v1/ssh-keys/%s', $this->id))->success();
    }
}