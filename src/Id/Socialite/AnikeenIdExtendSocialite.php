<?php

namespace Anikeen\Id\Socialite;

use SocialiteProviders\Manager\SocialiteWasCalled;

class AnikeenIdExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('anikeen-id', Provider::class);
    }
}