<?php

namespace Anikeen\Id\Helpers;

use Anikeen\Id\Result;
use stdClass;

class Paginator
{

    /**
     * Next desired action (first, after, before).
     */
    public ?string $action = null;

    /**
     * AnikeenId response pagination cursor.
     */
    private ?stdClass $pagination;

    /**
     * Constructor.
     *
     * @param null|stdClass $pagination AnikeenId response pagination cursor
     */
    public function __construct(?stdClass $pagination = null)
    {
        $this->pagination = $pagination;
    }

    /**
     * Create Paginator from Result object.
     */
    public static function from(Result $result): self
    {
        return new self($result->pagination);
    }

    /**
     * Return the current active cursor.
     */
    public function cursor(): string
    {
        return $this->pagination->cursor;
    }

    /**
     * Set the Paginator to fetch the next set of results.
     */
    public function first(): self
    {
        $this->action = 'first';

        return $this;
    }

    /**
     * Set the Paginator to fetch the first set of results.
     */
    public function next(): self
    {
        $this->action = 'after';

        return $this;
    }

    /**
     * Set the Paginator to fetch the last set of results.
     */
    public function back(): self
    {
        $this->action = 'before';

        return $this;
    }
}