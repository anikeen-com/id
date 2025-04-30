<?php

namespace Anikeen\Id;

use Anikeen\Id\Helpers\Paginator;
use Exception;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class Result
{
    /**
     * Was the API call successful?
     */
    public bool $success = false;

    /**
     * Response data: either an array of items (paginated) or a single object (non-paginated)
     */
    public mixed $data = [];

    /**
     * Total number of items: uses meta.total, root total, or falls back to count/data existence
     */
    public int $total = 0;

    /**
     * HTTP status code
     */
    public int $status = 0;

    /**
     * Pagination links (first, last, prev, next) as stdClass or null
     */
    public ?stdClass $links = null;

    /**
     * Pagination meta (current_page, last_page etc.) as stdClass or null
     */
    public ?stdClass $meta = null;

    /**
     * Paginator helper to retrieve next/prev pages
     */
    public ?Paginator $paginator = null;

    /**
     * Reference to the original AnikeenId client
     */
    public AnikeenId $anikeenId;

    /**
     * Constructor
     *
     * @param ResponseInterface|null $response
     * @param Exception|null         $exception
     * @param AnikeenId              $anikeenId
     */
    public function __construct(
        public ?ResponseInterface $response,
        public ?Exception $exception,
        AnikeenId $anikeenId
    ) {
        $this->anikeenId = $anikeenId;
        $this->success = $exception === null;
        $this->status = $response ? $response->getStatusCode() : 500;

        $raw = $response ? (string) $response->getBody() : null;
        $json = $raw ? @json_decode($raw, false) : null;

        if ($json !== null) {
            // Pagination info
            $this->links = $json->links ?? null;
            $this->meta  = $json->meta  ?? null;

            // Determine data shape
            if (isset($json->data)) {
                if ($this->links !== null || $this->meta !== null) {
                    // Paginated: always array
                    $this->data = is_array($json->data) ? $json->data : [$json->data];
                } else {
                    // Non-paginated: single object
                    $this->data = $json->data;
                }
            } else {
                // No 'data' key: treat entire payload
                if ($this->links !== null || $this->meta !== null) {
                    // Paginated but missing data key: fallback to empty array
                    $this->data = [];
                } else {
                    $this->data = $json;
                }
            }

            // Total items
            if (isset($json->meta->total)) {
                $this->total = (int) $json->meta->total;
            } elseif (isset($json->total)) {
                $this->total = (int) $json->total;
            } else {
                // count array or single object
                if (is_array($this->data)) {
                    $this->total = count($this->data);
                } elseif ($this->data !== null) {
                    $this->total = 1;
                }
            }

            // Initialize paginator only if pagination present
            if ($this->links !== null || $this->meta !== null) {
                $this->paginator = Paginator::from($this);
            }
        }
    }

    /**
     * Was the request successful?
     */
    public function success(): bool
    {
        return $this->success;
    }

    /**
     * Get last error message
     */
    public function error(): string
    {
        if ($this->exception === null || !method_exists($this->exception, 'getResponse')) {
            return 'Anikeen ID API Unavailable';
        }
        $resp = $this->exception->getResponse();
        $body = $resp ? (string) $resp->getBody() : null;
        $err  = $body ? @json_decode($body) : null;
        if (isset($err->message) && $err->message !== '') {
            return $err->message;
        }
        return $this->exception->getMessage();
    }

    /**
     * For paginated data: shift first element; for single object: return it
     */
    public function shift(): mixed
    {
        if (is_array($this->data)) {
            return array_shift($this->data);
        }
        return $this->data;
    }

    /**
     * Count of items in data
     */
    public function count(): int
    {
        if (is_array($this->data)) {
            return count($this->data);
        }
        return $this->data !== null ? 1 : 0;
    }

    /**
     * Fetch next page paginator
     */
    public function next(): ?Paginator
    {
        return $this->paginator?->next();
    }

    /**
     * Fetch previous page paginator
     */
    public function back(): ?Paginator
    {
        return $this->paginator?->back();
    }

    /**
     * Rate limit info from headers
     */
    public function rateLimit(?string $key = null): array|int|string|null
    {
        if (!$this->response) {
            return null;
        }
        $info = [
            'limit'     => (int) $this->response->getHeaderLine('X-RateLimit-Limit'),
            'remaining' => (int) $this->response->getHeaderLine('X-RateLimit-Remaining'),
            'reset'     => (int) $this->response->getHeaderLine('Retry-After'),
        ];
        return $key ? ($info[$key] ?? null) : $info;
    }

    /**
     * Insert related users into each data item (for arrays)
     */
    public function insertUsers(string $identifierAttribute = 'user_id', string $insertTo = 'user'): self
    {
        if (!is_array($this->data)) {
            return $this;
        }
        $ids = array_map(fn($item) => $item->{$identifierAttribute} ?? null, $this->data);
        $ids = array_filter($ids);
        if (empty($ids)) {
            return $this;
        }
        $users = $this->anikeenId->getUsersByIds($ids)->data;
        foreach ($this->data as &$item) {
            $item->{$insertTo} = collect($users)->firstWhere('id', $item->{$identifierAttribute});
        }
        return $this;
    }

    /**
     * Fetch first page paginator
     */
    public function first(): ?Paginator
    {
        return $this->paginator?->first();
    }

    /**
     * Original response
     */
    public function response(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Access raw data
     */
    public function data(): mixed
    {
        return $this->data;
    }
}
