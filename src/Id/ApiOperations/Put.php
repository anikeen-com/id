<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait Put
{
    /**
     * Make a PUT request to the API.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    abstract public function put(string $path, array $payload = [], array $parameters = [], ?Paginator $paginator = null): Result;
}