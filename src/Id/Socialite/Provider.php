<?php

namespace Anikeen\Id\Socialite;

use Anikeen\Id\Enums\Scope;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'ANIKEEN_ID';

    /**
     * {@inheritdoc}
     */
    protected $scopes = [Scope::USER_READ];

    /**
     * {@inherticdoc}
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the base URL for the API.
     */
    protected function getBaseUrl(): string
    {
        $mode = $this->config['mode'] ?? 'production';

        return $mode === 'staging'
            ? 'https://staging.id.anikeen.com'
            : 'https://id.anikeen.com';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            $this->getBaseUrl() . '/oauth/authorize', $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->getBaseUrl() . '/oauth/token';
    }

    /**
     * {@inheritdoc}
     *
     * @throws GuzzleException
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            $this->getBaseUrl() . '/api/v1/user', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true)['data'];
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): \Laravel\Socialite\Two\User|User
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['username'],
            'name' => $user['name'],
            'email' => Arr::get($user, 'email'),
            'avatar' => $user['avatar'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
