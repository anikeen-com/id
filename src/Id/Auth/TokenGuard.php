<?php

namespace Anikeen\Id\Auth;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\HasAnikeenTokens;
use Anikeen\Id\Helpers\JwtParser;
use Anikeen\Id\Result;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use stdClass;
use Throwable;

/**
 * Resolves the user of a request from an Anikeen ID access token (JWT, "Authorization: Bearer …")
 * or from the encrypted token cookie of CreateFreshApiToken.
 *
 * A bearer token is verified with the JwtParser (signature, expiry). Its `sub` is the user's
 * Anikeen ID, looked up by the provider (AnikeenIdUserProvider::retrieveByAnikeenId(), column
 * `anikeen_id` by default). The token is attached to the user: `$user->anikeenTokenCan('scope')`.
 *
 * With `services.anikeen.verify_revocation` the token is also checked against Anikeen ID
 * (GET v1/user, cached per token for `services.anikeen.revocation_cache_ttl` seconds), so
 * revoked tokens stop working before they expire.
 *
 * With AnikeenId::provisionUsersUsing() an unknown user of a valid bearer token is created by
 * the app's callback (just-in-time) instead of leaving the request unauthenticated.
 */
class TokenGuard
{
    /**
     * The user's profile from this request's revocation check (GET v1/user), if it ran.
     *
     * @var array<string, mixed>|null
     */
    private ?array $profile = null;

    public function __construct(
        protected UserProvider $provider,
        private Encrypter $encrypter,
        private JwtParser $jwtParser
    ) {
        //
    }

    /**
     * Get the user for the incoming request.
     */
    public function user(Request $request): ?Authenticatable
    {
        if ($request->bearerToken()) {
            return $this->authenticateViaBearerToken($request);
        } elseif ($request->cookie(AnikeenId::cookie())) {
            return $this->authenticateViaCookie($request);
        }

        return null;
    }

    /**
     * Authenticate the incoming request via the Bearer token.
     */
    protected function authenticateViaBearerToken(Request $request): ?Authenticatable
    {
        if (!$token = $this->validateRequestViaBearerToken($request)) {
            return null;
        }

        // client credentials tokens have no user: their `sub` is empty or (Passport / league
        // oauth2-server) the client's own id, i.e. equal to `aud`
        if (!is_scalar($token->sub ?? null) || (string) $token->sub === '' || $this->isClientCredentials($token)) {
            return null;
        }

        if (!$this->tokenIsActive($token, (string) $request->bearerToken())) {
            return null;
        }

        /** @var Authenticatable|HasAnikeenTokens|null $user */
        $user = $this->retrieveUser((string) $token->sub)
            ?? $this->provisionUser($token, (string) $request->bearerToken());

        return $user && method_exists($user, 'withAnikeenAccessToken')
            ? $user->withAnikeenAccessToken($token)
            : $user;
    }

    /**
     * Verify the Bearer token and expose its claims as request attributes.
     */
    protected function validateRequestViaBearerToken(Request $request): ?stdClass
    {
        try {
            $decoded = $this->jwtParser->decode($request);
        } catch (AuthenticationException) {
            return null;
        }

        $decoded->scopes = array_values(array_filter((array) ($decoded->scopes ?? []), 'is_string'));

        $request->attributes->set('oauth_access_token_id', $decoded->jti ?? null);
        $request->attributes->set('oauth_client_id', $decoded->aud ?? null);
        $request->attributes->set('oauth_client_trusted', $decoded->client->trusted ?? null);
        $request->attributes->set('oauth_client_name', $decoded->client->name ?? null);
        $request->attributes->set('oauth_user_id', $decoded->sub ?? null);
        $request->attributes->set('oauth_scopes', $decoded->scopes);

        return $decoded;
    }

    /**
     * A client credentials token: issued to an app for itself, `sub` = the client id (`aud`).
     */
    protected function isClientCredentials(stdClass $token): bool
    {
        $audience = $token->aud ?? null;
        $audience = is_array($audience) ? ($audience[0] ?? null) : $audience;

        return is_scalar($audience) && (string) $audience !== '' && (string) $token->sub === (string) $audience;
    }

    /**
     * Whether Anikeen ID still accepts the token (only with services.anikeen.verify_revocation).
     */
    protected function tokenIsActive(stdClass $token, string $jwt): bool
    {
        if (!config('services.anikeen.verify_revocation')) {
            return true;
        }

        $key = 'anikeen-id:token-active:' . hash('sha256', $jwt);

        if (($cached = Cache::get($key)) !== null) {
            return (bool) $cached;
        }

        try {
            $result = (clone app(AnikeenId::class))->withToken($jwt)->getAuthedUser();
        } catch (Throwable) {
            return false; // Anikeen ID unreachable: fail closed, ask again next time
        }

        $status = $result->response?->getStatusCode();

        if ($result->success()) {
            $active = true;
            $this->profile = $this->profileFrom($result);
        } elseif (in_array($status, [401, 403], true)) {
            $active = false;
        } else {
            return false; // server error: fail closed without caching
        }

        $ttl = (int) config('services.anikeen.revocation_cache_ttl', 300);
        if (isset($token->exp)) {
            $ttl = max(1, min($ttl, (int) $token->exp - time()));
        }
        Cache::put($key, $active, $ttl);

        return $active;
    }

    /**
     * Create the local user of a valid token through AnikeenId::provisionUsersUsing(), null
     * without a callback or when Anikeen ID does not give the user's profile.
     */
    protected function provisionUser(stdClass $token, string $jwt): ?Authenticatable
    {
        if (!$provisioner = AnikeenId::userProvisioner()) {
            return null;
        }

        if (!$profile = $this->profile ?? $this->fetchProfile($jwt)) {
            return null;
        }

        $user = $provisioner($token, $profile, $jwt);

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * The user's profile (GET v1/user with the token), null when Anikeen ID does not answer it.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchProfile(string $jwt): ?array
    {
        try {
            $result = (clone app(AnikeenId::class))->withToken($jwt)->getAuthedUser();
        } catch (Throwable) {
            return null;
        }

        return $result->success() ? $this->profileFrom($result) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profileFrom(Result $result): ?array
    {
        $profile = json_decode(json_encode($result->data()), true);

        return is_array($profile) && $profile !== [] ? $profile : null;
    }

    /**
     * Find the local user of an Anikeen ID.
     */
    protected function retrieveUser(string $anikeenId): ?Authenticatable
    {
        return $this->provider->retrieveByAnikeenId($anikeenId);
    }

    /**
     * Authenticate the incoming request via the token cookie.
     */
    protected function authenticateViaCookie(Request $request): ?Authenticatable
    {
        if (!$token = $this->getTokenViaCookie($request)) {
            return null;
        }

        // If this user exists, we will return this user and attach a "transient" token to
        // the user model. The transient token assumes it has all scopes since the user
        // is physically logged into the application via the application's interface.
        /** @var Authenticatable|HasAnikeenTokens|null $user */
        if ($user = $this->retrieveUser((string) $token['sub'])) {
            return method_exists($user, 'withAnikeenAccessToken')
                ? $user->withAnikeenAccessToken((object) ['scopes' => ['*']])
                : $user;
        }

        return null;
    }

    /**
     * Get the token cookie via the incoming request.
     */
    protected function getTokenViaCookie(Request $request): ?array
    {
        // If we need to retrieve the token from the cookie, it'll be encrypted so we must
        // first decrypt the cookie and then attempt to find the token value within the
        // database. If we can't decrypt the value we'll bail out with a null return.
        try {
            $token = $this->decodeJwtTokenCookie($request);
        } catch (Exception) {
            return null;
        }

        if (!isset($token['sub'], $token['expiry'])) {
            return null;
        }

        // We will compare the CSRF token in the decoded API token against the CSRF header
        // sent with the request. If they don't match then this request isn't sent from
        // a valid source and we won't authenticate the request for further handling.
        if (time() >= $token['expiry'] || (!AnikeenId::$ignoreCsrfToken && !$this->validCsrf($token, $request))) {
            return null;
        }

        return $token;
    }

    /**
     * Decode and decrypt the JWT token cookie.
     */
    protected function decodeJwtTokenCookie(Request $request): array
    {
        return (array) JWT::decode(
            CookieValuePrefix::remove($this->encrypter->decrypt($request->cookie(AnikeenId::cookie()), AnikeenId::$unserializesCookies)),
            new Key(
                $this->encrypter->getKey(),
                'HS256'
            )
        );
    }

    /**
     * Determine if the CSRF / header are valid and match.
     */
    protected function validCsrf(array $token, Request $request): bool
    {
        return isset($token['csrf']) && hash_equals(
            $token['csrf'], $this->getTokenFromRequest($request)
        );
    }

    /**
     * Get the CSRF token from the request.
     */
    protected function getTokenFromRequest(Request $request): string
    {
        $token = $request->header('X-CSRF-TOKEN');

        if (!$token && $header = $request->header('X-XSRF-TOKEN')) {
            $token = CookieValuePrefix::remove($this->encrypter->decrypt($header, static::serialized()));
        }

        return (string) $token;
    }

    /**
     * Determine if the cookie contents should be serialized.
     */
    public static function serialized(): bool
    {
        return EncryptCookies::serialized('XSRF-TOKEN');
    }
}
