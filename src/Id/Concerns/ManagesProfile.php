<?php

namespace Anikeen\Id\Concerns;

use Throwable;

trait ManagesProfile
{
    use HasBillable;

    /**
     * Get the profile url for the current user.
     *
     * @param string|null $returnUrl The URL to redirect to after the user has completed their profile.
     * @param array $options Additional options for the profile URL.
     * @return string
     * @throws Throwable
     */
    public function profilePortalUrl(?string $returnUrl = null, array $options = []): string
    {
        return $this->anikeenId()->request('POST', 'v1/user/profile', [
            'return_url' => $returnUrl,
            'options' => $options,
        ])->data->url;
    }
}