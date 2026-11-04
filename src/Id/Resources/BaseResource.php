<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\ConfirmationRequiredException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;
use JsonSerializable;

#[\AllowDynamicProperties]
abstract class BaseResource implements JsonSerializable
{
    public Result $result;

    /**
     * @throws ApiException the request failed (status, error code; a ResourceException)
     * @throws ConfirmationRequiredException the user has to confirm it on Anikeen ID
     */
    public function __construct(callable $callable)
    {
        try {
            $this->result = $callable();
        } catch (GuzzleException $e) {
            // no response at all (connection refused, timeout)
            throw ApiException::unreachable($e);
        }

        if (!$this->result->success()) {
            if (($this->result->data->error ?? null) === 'confirmation_required') {
                throw new ConfirmationRequiredException(
                    $this->result->data->message ?? 'The user has to confirm this on Anikeen ID.',
                    $this->result->response->getStatusCode(),
                    $this->result->data->confirmation_url ?? null,
                );
            }

            throw ApiException::fromResult($this->result);
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
