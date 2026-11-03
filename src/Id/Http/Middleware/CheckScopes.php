<?php

namespace Anikeen\Id\Http\Middleware;

use Anikeen\Id\Exceptions\MissingScopeException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckScopes extends UseParameters
{
    /**
     * Handle the incoming request.
     *
     * @throws AuthenticationException|MissingScopeException
     */
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        if (!$request->user() || !method_exists($request->user(), 'anikeenToken') || !$request->user()->anikeenToken()) {
            throw new AuthenticationException;
        }

        foreach ($scopes as $scope) {
            if (!$request->user()->anikeenTokenCan($scope)) {
                throw new MissingScopeException($scope);
            }
        }

        return $next($request);
    }
}
