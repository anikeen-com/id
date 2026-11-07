<?php

namespace Anikeen\Id\Enums;

/**
 * A user's role on Anikeen ID. Only trusted apps see it: `role` of GET v1/user (the Socialite
 * user), of a user read by ID and of user.updated; other apps get none.
 */
class Role
{
    /** Runs Anikeen (the Anikeen ID admin panel). */
    const ADMIN = 'admin';
    const USER = 'user';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::ADMIN, self::USER];
    }
}
