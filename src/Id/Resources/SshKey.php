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
    /**
     * Rename the SSH key.
     *
     * @throws Throwable
     */
    public function update(string $name): self
    {
        return (new self(fn() => $this->parent->put(sprintf('v1/ssh-keys/%s', $this->id), ['name' => $name])))->setParent($this->parent);
    }

    public function delete(): bool
    {
        return $this->parent->delete(sprintf('v1/ssh-keys/%s', $this->id))->success();
    }
}