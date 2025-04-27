<?php

namespace Anikeen\Id\Http\Middleware;

use Anikeen\Id\ApiTokenCookieFactory;
use Anikeen\Id\Facades\AnikeenId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreateFreshApiToken
{
    /**
     * The authentication guard.
     *
     * @var string
     */
    protected string $guard;

    /**
     * Create a new middleware instance.
     *
     * @return void
     */
    public function __construct(protected ApiTokenCookieFactory $cookieFactory)
    {
        //
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $guard = null): mixed
    {
        $this->guard = $guard;

        $response = $next($request);

        if ($this->shouldReceiveFreshToken($request, $response)) {
            $response->withCookie($this->cookieFactory->make(
                $request->user($this->guard)->getAuthIdentifier(), $request->session()->token()
            ));
        }

        return $response;
    }

    /**
     * Determine if the given request should receive a fresh token.
     */
    protected function shouldReceiveFreshToken(Request $request, Response $response): bool
    {
        return $this->requestShouldReceiveFreshToken($request) &&
            $this->responseShouldReceiveFreshToken($response);
    }

    /**
     * Determine if the request should receive a fresh token.
     */
    protected function requestShouldReceiveFreshToken(Request $request): bool
    {
        return $request->isMethod('GET') && $request->user($this->guard);
    }

    /**
     * Determine if the response should receive a fresh token.
     */
    protected function responseShouldReceiveFreshToken(Response $response): bool
    {
        return !$this->alreadyContainsToken($response);
    }

    /**
     * Determine if the given response already contains an API token.
     * This avoids us overwriting a just "refreshed" token.
     */
    protected function alreadyContainsToken(Response $response): bool
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === AnikeenId::cookie()) {
                return true;
            }
        }

        return false;
    }
}