<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\MagicProperties;
use Anikeen\Id\Result;

abstract class BaseResource
{
    use MagicProperties;

    public function __construct(protected Result $result)
    {
        $this->setMagicProperties($this->result->data);
    }
}