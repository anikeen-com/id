<?php

namespace Anikeen\Id;

use stdClass;

trait HasAnikeenTokens
{
    /**
     * The current access token for the authentication user.
     */
    protected ?stdClass $accessToken = null;

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
        $scopes = (array) ($this->accessToken->scopes ?? []);

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }

    /**
     * Determine if the current API token has any of the given scopes.
     */
    public function anikeenTokenCanAny(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->anikeenTokenCan($scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The OAuth client (app) the current token was issued to (its `aud`), or null (e.g. the token
     * cookie, AnikeenId::actingAs() without claims).
     */
    public function anikeenTokenClientId(): ?string
    {
        $audience = $this->accessToken->aud ?? null;

        if (is_array($audience)) {
            $audience = $audience[0] ?? null;
        }

        return is_scalar($audience) && (string) $audience !== '' ? (string) $audience : null;
    }

    /**
     * The name of the app the current token was issued to (`client.name`), or null.
     */
    public function anikeenTokenClientName(): ?string
    {
        $name = $this->clientClaim()['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Whether the current token was issued to an app Anikeen trusts (`client.trusted`). False when
     * the token does not say so, e.g. tokens issued before Anikeen ID sent the claim.
     */
    public function anikeenTokenClientTrusted(): bool
    {
        return ($this->clientClaim()['trusted'] ?? null) === true;
    }

    /**
     * Whether the current token is a personal access token (`client.personal_access`), which a
     * user created for themselves rather than an app. False when the token does not say so.
     */
    public function anikeenTokenIsPersonalAccess(): bool
    {
        return ($this->clientClaim()['personal_access'] ?? null) === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientClaim(): array
    {
        $client = $this->accessToken->client ?? null;

        return is_object($client) || is_array($client) ? (array) $client : [];
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