<?php

namespace Anikeen\Id\Contracts;

use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

interface Billable
{
    /**
     * Get the user data of the billable.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function getUserData(): ?stdClass;

    /**
     * Send a request to the API.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function request(string $method, string $path, null|array $payload = null, array $parameters = [], ?Paginator $paginator = null): Result;
}