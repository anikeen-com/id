<?php

namespace Anikeen\Id\Helpers;

use Anikeen\Id\Result;
use stdClass;

class Paginator
{
    /**
     * Next desired action: 'first', 'after', 'before'.
     *
     * @var string|null
     */
    public ?string $action = null;

    /**
     * Raw pagination links from API ('first','last','prev','next').
     *
     * @var stdClass|null
     */
    private ?stdClass $links;

    /**
     * Raw pagination meta from API (current_page, last_page, etc.).
     *
     * @var stdClass|null
     */
    private ?stdClass $meta;

    /**
     * Constructor.
     *
     * @param stdClass|null $links Pagination links object
     * @param stdClass|null $meta  Pagination meta object
     */
    public function __construct(?stdClass $links = null, ?stdClass $meta = null)
    {
        $this->links = $links;
        $this->meta = $meta;
    }

    /**
     * Create Paginator from a Result instance.
     */
    public static function from(Result $result): self
    {
        return new self($result->links, $result->meta);
    }

    /**
     * Return the cursor value (page number) based on the last set action.
     */
    public function cursor(): string
    {
        switch ($this->action) {
            case 'first':
                return '1';

            case 'after':
                // Try parsing from 'next' link
                if ($this->links && !empty($this->links->next)) {
                    return $this->parsePageFromUrl($this->links->next);
                }
                // Fallback to current_page + 1
                return isset($this->meta->current_page)
                    ? (string)($this->meta->current_page + 1)
                    : '1';

            case 'before':
                if ($this->links && !empty($this->links->prev)) {
                    return $this->parsePageFromUrl($this->links->prev);
                }
                // Fallback to current_page - 1
                return isset($this->meta->current_page)
                    ? (string)($this->meta->current_page - 1)
                    : '1';

            default:
                // Default to current page
                return isset($this->meta->current_page)
                    ? (string)$this->meta->current_page
                    : '1';
        }
    }

    /**
     * Parse the 'page' query parameter from a URL.
     */
    private function parsePageFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (empty($parts['query'])) {
            return '1';
        }
        parse_str($parts['query'], $vars);
        return $vars['page'] ?? '1';
    }

    /**
     * Fetch the first page.
     */
    public function first(): self
    {
        $this->action = 'first';
        return $this;
    }

    /**
     * Fetch the next page (after).
     */
    public function next(): self
    {
        $this->action = 'after';
        return $this;
    }

    /**
     * Fetch the previous page (before).
     */
    public function back(): self
    {
        $this->action = 'before';
        return $this;
    }
}