<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait Request
{
    /**
     * Make a request to the API.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    abstract public function request(string $method, string $path, null|array $payload = null, array $parameters = [], Paginator $paginator = null): Result;
}