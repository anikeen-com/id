<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasParent;
use Throwable;

/**
 * @property string $id
 */
class SshKey extends BaseResource
{
    use HasParent;

    /**
     * Deletes a given ssh key for the currently authed user.
     *
     * @throws Throwable
     */
    public function delete(): bool
    {
        return $this->parent->delete(sprintf('v1/ssh-keys/%s', $this->id))->success();
    }
}