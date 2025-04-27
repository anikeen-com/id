<?php

namespace Anikeen\Id\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use stdClass;
use Throwable;

class JwtParser
{
    /**
     * @throws AuthenticationException
     */
    public function decode(Request $request): stdClass
    {
        JWT::$leeway = 60;

        try {
            return JWT::decode(
                $request->bearerToken(),
                new Key($this->getOauthPublicKey(), 'RS256')
            );
        } catch (Throwable $exception) {
            throw (new AuthenticationException());
        }
    }

    private function getOauthPublicKey(): bool|string
    {
        return file_get_contents(dirname(__DIR__, 3) . '/oauth-public.key');
    }
}
