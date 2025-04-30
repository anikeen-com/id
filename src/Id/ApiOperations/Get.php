<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait Get
{
    /**
     * Get a resource from the API.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    abstract public function get(string $path, array $parameters = [], ?Paginator $paginator = null): Result;
}