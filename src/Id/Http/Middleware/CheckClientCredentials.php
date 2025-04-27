<?php

namespace Anikeen\Id\Http\Middleware;

use Anikeen\Id\Exceptions\MissingScopeException;
use stdClass;

class CheckClientCredentials extends CheckCredentials
{
    /**
     * Validate token credentials.
     *
     * @throws MissingScopeException
     */
    protected function validateScopes(stdClass $token, array $scopes): void
    {
        if (in_array('*', $token->scopes)) {
            return;
        }

        foreach ($scopes as $scope) {
            if (!in_array($scope, $token->scopes)) {
                throw new MissingScopeException($scopes);
            }
        }
    }
}