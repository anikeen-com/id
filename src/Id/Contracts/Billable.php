<?php

namespace Anikeen\Id\Contracts;

use Anikeen\Id\AnikeenId;

interface Billable
{
    public function getUserData(): object;

    public function anikeenId(): AnikeenId;
}