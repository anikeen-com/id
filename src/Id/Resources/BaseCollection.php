<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Exceptions\CollectionException;
use Anikeen\Id\Exceptions\ResourceException;
use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;
use Closure;
use GuzzleHttp\Psr7\Response;
use JsonSerializable;
use Throwable;

/**
 * @property bool $success
 * @property mixed $data
 * @property int $total
 * @property int $status
 * @property null|array $links
 * @property null|array $meta
 * @property null|Paginator $paginator
 * @property AnikeenId $anikeenId
 * @property Response $response
 * @property null|Throwable $exception
 */
abstract class BaseCollection implements JsonSerializable
{
    private Closure $callable;
    public ?Result $result = null;

    /**
     * @throws CollectionException
     */
    protected function __construct(callable $callable)
    {
        $this->result = $callable();

        if (!$this->result->success()) {
            throw new CollectionException(sprintf('%s for collection [%s]', rtrim($this->result->data->message, '.'), get_called_class()), $this->result->response->getStatusCode());
        }
    }

    /**
     * @throws CollectionException
     */
    public static function builder(callable $callable): static
    {
        return new static($callable, false);
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

    public function __get(string $name)
    {
        return $this->result->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->result->{$name});
    }
}