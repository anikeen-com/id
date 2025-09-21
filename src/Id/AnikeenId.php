<?php

namespace Anikeen\Id;

use Anikeen\Id\Concerns\ManagesPricing;
use Anikeen\Id\Concerns\ManagesSshKeys;
use Anikeen\Id\Concerns\ManagesUsers;
use Anikeen\Id\Exceptions\RequestRequiresAuthenticationException;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Exceptions\RequestRequiresRedirectUriException;
use Anikeen\Id\Helpers\Paginator;
use Anikeen\Support\Query;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Auth\Authenticatable;


class AnikeenId
{
    use OauthTrait;
    use ManagesPricing;
    use ManagesSshKeys;
    use ManagesUsers;

    use ApiOperations\Delete;
    use ApiOperations\Get;
    use ApiOperations\Post;
    use ApiOperations\Put;
    use ApiOperations\Request;

    /**
     * The name for API token cookies.
     *
     * @var string
     */
    public static string $cookie = 'anikeen_id_token';

    /**
     * Indicates if Anikeen ID should ignore incoming CSRF tokens.
     */
    public static bool $ignoreCsrfToken = false;

    /**
     * Indicates if Anikeen ID should unserializes cookies.
     */
    public static bool $unserializesCookies = false;

    /**
     * The key for the access token.
     */
    private static string $accessTokenField = 'anikeen_id_access_token';

    /**
     * The key for the access token.
     */
    private static string $refreshTokenField = 'anikeen_id_refresh_token';

    /**
     * Guzzle is used to make http requests.
     */
    protected Client $client;

    /**
     * Paginator object.
     */
    protected Paginator $paginator;

    /**
     * Anikeen ID OAuth token.
     */
    protected ?string $token = null;

    /**
     * Anikeen ID client id.
     */
    protected ?string $clientId = null;

    /**
     * Anikeen ID client secret.
     */
    protected ?string $clientSecret = null;

    /**
     * Anikeen ID OAuth redirect url.
     */
    protected ?string $redirectUri = null;

    /**
     * The base URL for Anikeen ID.
     */
    protected string $baseUrl = 'https://id.anikeen.com';

    /**
     * The staging base URL for Anikeen ID.
     */
    protected string $stagingBaseUrl = 'https://staging.id.anikeen.com';

    /**
     * Constructor.
     */
    public function __construct()
    {
        if ($clientId = config('services.anikeen.client_id')) {
            $this->setClientId($clientId);
        }
        if ($clientSecret = config('services.anikeen.client_secret')) {
            $this->setClientSecret($clientSecret);
        }
        if ($redirectUri = config('services.anikeen.redirect')) {
            $this->setRedirectUri($redirectUri);
        }
        if (self::getMode() === 'staging' && !config('services.anikeen.base_url')) {
            self::setBaseUrl($this->stagingBaseUrl);
        }
        if ($baseUrl = config('services.anikeen.base_url')) {
            self::setBaseUrl($baseUrl);
        }
        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/api/',
        ]);
    }

    protected function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public static function useAccessTokenField(string $accessTokenField): void
    {
        self::$accessTokenField = $accessTokenField;
    }

    public static function getAccessTokenField(): string
    {
        return self::$accessTokenField;
    }

    public static function getMode(): string
    {
        return config('services.anikeen.mode') ?: 'production';
    }

    public static function useRefreshTokenField(string $refreshTokenField): void
    {
        self::$refreshTokenField = $refreshTokenField;
    }

    public static function getRefreshTokenField(): string
    {
        return self::$refreshTokenField;
    }

    /**
     * Get or set the name for API token cookies.
     *
     * @param string|null $cookie
     * @return string|static
     */
    public static function cookie(?string $cookie = null): string|static
    {
        if (is_null($cookie)) {
            return static::$cookie;
        }

        static::$cookie = $cookie;

        return new static;
    }

    /**
     * Set the current user for the application with the given scopes.
     */
    public static function actingAs(Authenticatable|HasAnikeenTokens $user, array $scopes = [], string $guard = 'api'): Authenticatable
    {
        $user->withAnikeenAccessToken((object)[
            'scopes' => $scopes
        ]);

        if (isset($user->wasRecentlyCreated) && $user->wasRecentlyCreated) {
            $user->wasRecentlyCreated = false;
        }

        app('auth')->guard($guard)->setUser($user);

        app('auth')->shouldUse($guard);

        return $user;
    }

    /**
     * Fluid client id setter.
     */
    public function withClientId(string $clientId): self
    {
        $this->setClientId($clientId);

        return $this;
    }

    /**
     * Get client secret.
     *
     * @throws RequestRequiresClientIdException
     */
    public function getClientSecret(): string
    {
        if (!$this->clientSecret) {
            throw new RequestRequiresClientIdException;
        }

        return $this->clientSecret;
    }

    /**
     * Set client secret.
     */
    public function setClientSecret(string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * Fluid client secret setter.
     */
    public function withClientSecret(string $clientSecret): self
    {
        $this->setClientSecret($clientSecret);

        return $this;
    }

    /**
     * Get redirect url.
     *
     * @throws RequestRequiresRedirectUriException
     */
    public function getRedirectUri(): string
    {
        if (!$this->redirectUri) {
            throw new RequestRequiresRedirectUriException;
        }

        return $this->redirectUri;
    }

    /**
     * Set redirect url.
     */
    public function setRedirectUri(string $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
    }

    /**
     * Fluid redirect url setter.
     */
    public function withRedirectUri(string $redirectUri): self
    {
        $this->setRedirectUri($redirectUri);

        return $this;
    }

    /**
     * Get OAuth token.
     *
     * @throws RequestRequiresAuthenticationException
     */
    public function getToken(): ?string
    {
        if (!$this->token) {
            throw new RequestRequiresAuthenticationException;
        }

        return $this->token;
    }

    /**
     * Set OAuth token.
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Fluid OAuth token setter.
     */
    public function withToken(string $token): self
    {
        $this->setToken($token);

        return $this;
    }

    /**
     * Get client id.
     *
     * @throws RequestRequiresClientIdException
     */
    public function getClientId(): string
    {
        if (!$this->clientId) {
            throw new RequestRequiresClientIdException;
        }

        return $this->clientId;
    }

    /**
     * Set client id.
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * Build query & execute.
     *
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function request(string $method, string $path, null|array $payload = null, array $parameters = [], ?Paginator $paginator = null, bool $useClientSecret = false): Result
    {
        if ($paginator !== null) {
            $parameters[$paginator->action] = $paginator->cursor();
        }

        try {
            $response = $this->client->request($method, $path, [
                'headers' => $this->buildHeaders((bool)$payload, $useClientSecret),
                'query' => Query::build($parameters),
                'json' => $payload ?: null,
            ]);

            $result = new Result($response, null, $this);
        } catch (RequestException $exception) {
            $result = new Result($exception->getResponse(), $exception, $this);
        }

        return $result;
    }

    /**
     * Build headers for request.
     *
     * @throws RequestRequiresClientIdException
     */
    private function buildHeaders(bool $json = false, bool $useClientSecret = false): array
    {
        $headers = [
            'Client-ID' => $this->getClientId(),
            'Accept' => 'application/json',
        ];
        if ($bearerToken = $useClientSecret ? $this->getClientSecret() : $this->getToken()) {
            $headers['Authorization'] = 'Bearer ' . $bearerToken;
        }
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function get(string $path, array $parameters = [], ?Paginator $paginator = null): Result
    {
        return $this->request('GET', $path, null, $parameters, $paginator);
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function post(string $path, array $payload = [], array $parameters = [], ?Paginator $paginator = null): Result
    {
        return $this->request('POST', $path, $payload, $parameters, $paginator);
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function put(string $path, array $payload = [], array $parameters = [], ?Paginator $paginator = null): Result
    {
        return $this->request('PUT', $path, $payload, $parameters, $paginator);
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function delete(string $path, array $payload = [], array $parameters = [], ?Paginator $paginator = null): Result
    {
        return $this->request('DELETE', $path, $payload, $parameters, $paginator);
    }
}
