<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasParent;

class SocialiteUsers extends BaseCollection
{
    use HasParent;

    public function find(string $id): ?SocialiteUser
    {
        return null;
    }
}
