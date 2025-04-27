<?php

namespace Anikeen\Id;

use Anikeen\Id\Helpers\Paginator;
use Exception;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class Result
{

    /**
     * Query successful.
     */
    public bool $success = false;

    /**
     * Query result data.
     */
    public array $data = [];

    /**
     * Total amount of result data.
     */
    public int $total = 0;

    /**
     * Status Code.
     */
    public int $status = 0;

    /**
     * AnikeenId response pagination cursor.
     */
    public ?stdClass $pagination;

    /**
     * Original AnikeenId instance.
     *
     * @var AnikeenId
     */
    public AnikeenId $anikeenId;

    public function __construct(public ?ResponseInterface $response, public ?Exception $exception = null, public ?Paginator $paginator = null)
    {
        $this->success = $exception === null;
        $this->status = $response ? $response->getStatusCode() : 500;
        $jsonResponse = $response ? @json_decode($response->getBody()->getContents(), false) : null;
        if ($jsonResponse !== null) {
            $this->setProperty($jsonResponse, 'data');
            $this->setProperty($jsonResponse, 'total');
            $this->setProperty($jsonResponse, 'pagination');
            $this->paginator = Paginator::from($this);
        }
    }

    /**
     * Sets a class attribute by given JSON Response Body.
     */
    private function setProperty(stdClass $jsonResponse, string $responseProperty, string $attribute = null): void
    {
        $classAttribute = $attribute ?? $responseProperty;
        if (property_exists($jsonResponse, $responseProperty)) {
            $this->{$classAttribute} = $jsonResponse->{$responseProperty};
        } elseif ($responseProperty === 'data') {
            $this->{$classAttribute} = $jsonResponse;
        }
    }

    /**
     * Returns whether the query was successfully.
     */
    public function success(): bool
    {
        return $this->success;
    }

    /**
     * Returns the last HTTP or API error.
     */
    public function error(): string
    {
        // TODO Switch Exception response parsing to this->data
        if ($this->exception === null || !$this->exception->hasResponse()) {
            return 'Anikeen ID API Unavailable';
        }
        $exception = (string)$this->exception->getResponse()->getBody();
        $exception = @json_decode($exception);
        if (property_exists($exception, 'message') && !empty($exception->message)) {
            return $exception->message;
        }

        return $this->exception->getMessage();
    }

    /**
     * Shifts the current result (Use for single user/video etc. query).
     */
    public function shift(): mixed
    {
        if (!empty($this->data)) {
            $data = $this->data;

            return array_shift($data);
        }

        return null;
    }

    /**
     * Return the current count of items in dataset.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Set the Paginator to fetch the next set of results.
     */
    public function next(): ?Paginator
    {
        return $this->paginator?->next();
    }

    /**
     * Set the Paginator to fetch the last set of results.
     */
    public function back(): ?Paginator
    {
        return $this->paginator?->back();
    }

    /**
     * Get rate limit information.
     */
    public function rateLimit(string $key = null): array|int|string|null
    {
        if (!$this->response) {
            return null;
        }
        $rateLimit = [
            'limit' => (int)$this->response->getHeaderLine('X-RateLimit-Limit'),
            'remaining' => (int)$this->response->getHeaderLine('X-RateLimit-Remaining'),
            'reset' => (int)$this->response->getHeaderLine('Retry-After'),
        ];
        if ($key === null) {
            return $rateLimit;
        }

        return $rateLimit[$key];
    }

    /**
     * Insert users in data response.
     */
    public function insertUsers(string $identifierAttribute = 'user_id', string $insertTo = 'user'): self
    {
        $data = $this->data;
        $userIds = collect($data)->map(function ($item) use ($identifierAttribute) {
            return $item->{$identifierAttribute};
        })->toArray();
        if (count($userIds) === 0) {
            return $this;
        }
        $users = collect($this->anikeenId->getUsersByIds($userIds)->data);
        $dataWithUsers = collect($data)->map(function ($item) use ($users, $identifierAttribute, $insertTo) {
            $item->$insertTo = $users->where('id', $item->{$identifierAttribute})->first();

            return $item;
        });
        $this->data = $dataWithUsers->toArray();

        return $this;
    }

    /**
     * Set the Paginator to fetch the first set of results.
     */
    public function first(): ?Paginator
    {
        return $this->paginator?->first();
    }

    public function response(): ?ResponseInterface
    {
        return $this->response;
    }

    public function dump(): void
    {
        dump($this->data());
    }

    /**
     * Get the response data, also available as public attribute.
     */
    public function data(): array
    {
        return $this->data;
    }
}