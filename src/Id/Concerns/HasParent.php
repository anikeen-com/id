<?php

namespace Anikeen\Id\Concerns;

trait HasParent
{
    protected mixed $parent;

    public function setParent(mixed $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParent()
    {
        return $this->parent;
    }
}