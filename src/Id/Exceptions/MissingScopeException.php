<?php

namespace Anikeen\Id\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class MissingScopeException extends AuthorizationException
{
    /**
     * The scopes that the user did not have.
     */
    protected array $scopes;

    /**
     * Create a new missing scope exception.
     */
    public function __construct(array|string $scopes = [], $message = 'Invalid scope(s) provided.')
    {
        parent::__construct($message);

        $this->scopes = Arr::wrap($scopes);
    }

    /**
     * Get the scopes that the user did not have.
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}