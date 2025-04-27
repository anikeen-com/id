<?php

namespace Anikeen\Id\Repository;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Contracts\AppTokenRepository as Repository;
use Anikeen\Id\Exceptions\RequestFreshAccessTokenException;
use Illuminate\Support\Facades\Cache;

class AppTokenRepository implements Repository
{
    public const ACCESS_TOKEN_CACHE_KEY = 'anikeen-id:access_token';

    private AnikeenId $client;

    public function __construct()
    {
        $this->client = app(AnikeenId::class);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessToken(): string
    {
        $accessToken = Cache::get(self::ACCESS_TOKEN_CACHE_KEY);

        if ($accessToken) {
            return $accessToken;
        }

        return $this->requestFreshAccessToken('*');
    }

    /**
     * @throws RequestFreshAccessTokenException
     */
    private function requestFreshAccessToken(string $scope): mixed
    {
        $result = $this->getClient()->retrievingToken('client_credentials', [
            'scope' => $scope,
        ]);

        if (!$result->success()) {
            throw RequestFreshAccessTokenException::fromResponse($result->response());
        }

        Cache::put(self::ACCESS_TOKEN_CACHE_KEY, $accessToken = $result->data()->access_token, now()->addWeek());

        return $accessToken;
    }

    private function getClient(): AnikeenId
    {
        return $this->client;
    }
}