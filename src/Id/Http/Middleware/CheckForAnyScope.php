<?php

namespace Anikeen\Id\Http\Middleware;

use Anikeen\Id\Exceptions\MissingScopeException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckForAnyScope extends UseParameters
{
    /**
     * Handle the incoming request.
     *
     * @throws AuthenticationException|MissingScopeException
     */
    public function handle(Request $request, Closure $next, ...$scopes): Response
    {
        if (!$request->user() || !$request->user()->anikeenToken()) {
            throw new AuthenticationException;
        }

        foreach ($scopes as $scope) {
            if ($request->user()->anikeenTokenCan($scope)) {
                return $next($request);
            }
        }

        throw new MissingScopeException($scopes);
    }
}