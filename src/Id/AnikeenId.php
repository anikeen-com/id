<?php

namespace Anikeen\Id;

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
    use Traits\OauthTrait;
    use Traits\SshKeysTrait;
    use Traits\UsersTrait;

    use ApiOperations\Delete;
    use ApiOperations\Get;
    use ApiOperations\Post;
    use ApiOperations\Put;

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

    private static string $baseUrl = 'https://id.anikeen.com/api/';

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
     *
     */
    protected ?string $token = null;

    /**
     * Anikeen ID client id.
     *
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
     * Constructor.
     */
    public function __construct()
    {
        if ($clientId = config('anikeen_id.client_id')) {
            $this->setClientId($clientId);
        }
        if ($clientSecret = config('anikeen_id.client_secret')) {
            $this->setClientSecret($clientSecret);
        }
        if ($redirectUri = config('anikeen_id.redirect_url')) {
            $this->setRedirectUri($redirectUri);
        }
        if ($redirectUri = config('anikeen_id.base_url')) {
            self::setBaseUrl($redirectUri);
        }
        $this->client = new Client([
            'base_uri' => self::$baseUrl,
        ]);
    }

    /**
     * @param string $baseUrl
     *
     * @internal only for internal and debug purposes.
     */
    public static function setBaseUrl(string $baseUrl): void
    {
        self::$baseUrl = $baseUrl;
    }

    /**
     * Get or set the name for API token cookies.
     *
     * @param string|null $cookie
     * @return string|static
     */
    public static function cookie(string $cookie = null)
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
    public static function actingAs(Authenticatable|Traits\HasAnikeenTokens $user, array $scopes = [], string $guard = 'api'): Authenticatable
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
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function get(string $path = '', array $parameters = [], Paginator $paginator = null): Result
    {
        return $this->query('GET', $path, $parameters, $paginator);
    }

    /**
     * Build query & execute.
     *
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function query(string $method = 'GET', string $path = '', array $parameters = [], Paginator $paginator = null, mixed $jsonBody = null): Result
    {
        /** @noinspection DuplicatedCode */
        if ($paginator !== null) {
            $parameters[$paginator->action] = $paginator->cursor();
        }
        try {
            $response = $this->client->request($method, $path, [
                'headers' => $this->buildHeaders((bool)$jsonBody),
                'query' => Query::build($parameters),
                'json' => $jsonBody ?: null,
            ]);
            $result = new Result($response, null, $paginator);
        } catch (RequestException $exception) {
            $result = new Result($exception->getResponse(), $exception, $paginator);
        }
        $result->anikeenId = $this;

        return $result;
    }

    /**
     * Build headers for request.
     *
     * @throws RequestRequiresClientIdException
     */
    private function buildHeaders(bool $json = false): array
    {
        $headers = [
            'Client-ID' => $this->getClientId(),
            'Accept' => 'application/json',
        ];
        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
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
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function post(string $path = '', array $parameters = [], Paginator $paginator = null): Result
    {
        return $this->query('POST', $path, $parameters, $paginator);
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function delete(string $path = '', array $parameters = [], Paginator $paginator = null): Result
    {
        return $this->query('DELETE', $path, $parameters, $paginator);
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function put(string $path = '', array $parameters = [], Paginator $paginator = null): Result
    {
        return $this->query('PUT', $path, $parameters, $paginator);
    }

    /**
     * @throws GuzzleException
     * @throws RequestRequiresClientIdException
     */
    public function json(string $method, string $path = '', array $body = null): Result
    {
        if ($body) {
            $body = json_encode(['data' => $body]);
        }

        return $this->query($method, $path, [], null, $body);
    }
}
