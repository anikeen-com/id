<?php

namespace Anikeen\Id\Contracts;

use Anikeen\Id\Exceptions\RequestFreshAccessTokenException;

interface AppTokenRepository
{
    /**
     * @throws RequestFreshAccessTokenException
     */
    public function getAccessToken(): string;
}