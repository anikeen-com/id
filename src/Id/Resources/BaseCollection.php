<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;
use JsonSerializable;

abstract class BaseCollection implements JsonSerializable
{
    public function __construct(protected Result $result)
    {
        //
    }

    /**
     * Returns the collection of resources as an array.
     */
    public function toArray(): array
    {
        return (array) $this->result;
    }

    /**
     * Returns the collection of resources as a JSON string.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the collection of resources.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function get(): Result
    {
        return $this->result;
    }

    /**
     * Returns the Resource based on the ID.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    abstract public function find(string $id): ?BaseResource;
}