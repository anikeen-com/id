<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Get;
use Anikeen\Id\Resources\SocialiteUsers;
use Throwable;

trait ManagesSocialiteUsers
{
    use Get;

    /**
     * Get all socialite connections for the authenticated user.
     *
     * @throws Throwable
     */
    public function socialiteUsers(): SocialiteUsers
    {
        return SocialiteUsers::builder(fn() => $this->get('v1/user/socialite-users'))
            ->setParent($this);
    }
}
