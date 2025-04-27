<?php

namespace Anikeen\Id\Traits;

use stdClass;

trait HasAnikeenTokens
{
    /**
     * The current access token for the authentication user.
     */
    protected ?stdClass $accessToken;

    /**
     * Get the current access token being used by the user.
     *
     * @return stdClass|null
     */
    public function anikeenToken(): ?stdClass
    {
        return $this->accessToken;
    }

    /**
     * Determine if the current API token has a given scope.
     */
    public function anikeenTokenCan(string $scope): bool
    {
        $scopes = $this->accessToken ? $this->accessToken->scopes : [];

        return in_array('*', $scopes) || in_array($scope, $this->accessToken->scopes);
    }

    /**
     * Set the current access token for the user.
     */
    public function withAnikeenAccessToken(stdClass $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}