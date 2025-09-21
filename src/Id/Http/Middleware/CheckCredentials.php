<?php

namespace Anikeen\Id\Http\Middleware;

use Anikeen\Id\Exceptions\MissingScopeException;
use Anikeen\Id\Helpers\JwtParser;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use stdClass;

abstract class CheckCredentials extends UseParameters
{
    /**
     * Handle an incoming request.
     *
     * @throws AuthenticationException|MissingScopeException
     */
    public function handle(Request $request, Closure $next, ...$scopes): mixed
    {
        $decoded = $this->getJwtParser()->decode($request);

        $request->attributes->set('oauth_access_token_id', $decoded->jti);
        $request->attributes->set('oauth_client_id', $decoded->aud);
        //$request->attributes->set('oauth_client_trusted', $decoded->client->trusted);
        $request->attributes->set('oauth_user_id', $decoded->sub);
        $request->attributes->set('oauth_scopes', $decoded->scopes);

        $this->validateScopes($decoded, $scopes);

        return $next($request);
    }

    private function getJwtParser(): JwtParser
    {
        return app(JwtParser::class);
    }

    abstract protected function validateScopes(stdClass $token, array $scopes);
}