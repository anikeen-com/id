<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\ResourceException;
use Anikeen\Id\Result;
use JsonSerializable;

abstract class BaseResource implements JsonSerializable
{
    public Result $result;

    /**
     * @throws ResourceException
     */
    public function __construct(callable $callable)
    {
        $this->result = $callable();

        if (!$this->result->success()) {
            throw new ResourceException(sprintf('%s for resource [%s]', rtrim($this->result->data->message, '.'), get_called_class()), $this->result->response->getStatusCode());
        }

        foreach ($this->result->data as $key => $value) {
            if (!property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Returns the collection of resources as a JSON string.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the collection of resources as an array.
     */
    public function toArray(): array
    {
        return (array)$this->result->data;
    }

    public function __get(string $name)
    {
        return null;
    }

    public function __isset(string $name): bool
    {
        return false;
    }
}