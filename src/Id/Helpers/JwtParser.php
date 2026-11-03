<?php

namespace Anikeen\Id\Helpers;

use Anikeen\Id\AnikeenId;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Verifies access tokens issued by Anikeen ID (Passport, RS256): signature against
 * Anikeen ID's public key plus the time claims (exp, nbf, iat; 60 seconds leeway).
 *
 * The key is `services.anikeen.public_key` (PEM, "\n" escapes allowed) or the file at
 * `services.anikeen.public_key_path`, else the key shipped with this package for the
 * configured mode (production / staging). It is read once per process.
 */
class JwtParser
{
    /**
     * Public keys already read, by source.
     *
     * @var array<string, string>
     */
    private static array $keys = [];

    /**
     * @throws AuthenticationException
     */
    public function decode(Request $request): stdClass
    {
        return $this->decodeToken((string) $request->bearerToken());
    }

    /**
     * @throws AuthenticationException
     */
    public function decodeToken(string $token): stdClass
    {
        if ($token === '') {
            throw new AuthenticationException('No access token given.');
        }

        JWT::$leeway = 60;

        // outside the try: a missing key is a configuration error, not an invalid token
        $key = new Key($this->getOauthPublicKey(), 'RS256');

        try {
            return JWT::decode($token, $key);
        } catch (Throwable) {
            throw new AuthenticationException('The access token is invalid or expired.');
        }
    }

    /**
     * Forget the keys read so far (e.g. after changing the configuration in tests).
     */
    public static function flushKeys(): void
    {
        self::$keys = [];
    }

    protected function getOauthPublicKey(): string
    {
        if ($key = config('services.anikeen.public_key')) {
            return self::$keys['config:' . md5($key)] ??= str_replace('\\n', "\n", $key);
        }

        $path = config('services.anikeen.public_key_path') ?: (AnikeenId::getMode() === 'staging'
            ? dirname(__DIR__, 3) . '/oauth-public.staging.key'
            : dirname(__DIR__, 3) . '/oauth-public.key');

        return self::$keys['file:' . $path] ??= (is_readable($path) ? (string) file_get_contents($path) : throw new RuntimeException("Anikeen ID public key [$path] is not readable."));
    }
}
