<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Result;
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
        return (array)$this->result->data;
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
     */
    public function paginate(): Result
    {
        return $this->result;
    }

    /**
     * Returns the Resource based on the ID.
     */
    abstract public function find(string $id): ?BaseResource;
}