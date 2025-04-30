<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\MagicProperties;
use Anikeen\Id\Result;
use JsonSerializable;

abstract class BaseResource implements JsonSerializable
{
    use MagicProperties;

    public function __construct(protected Result $result)
    {
        $this->setMagicProperties($this->result->data);
    }

    /**
     * Returns the collection of resources as an array.
     */
    public function toArray(): array
    {
        return (array) $this->result->data;
    }

    /**
     * Returns the collection of resources as a JSON string.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}