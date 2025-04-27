<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;

trait Get
{
    abstract public function get(string $path = '', array $parameters = [], Paginator $paginator = null): Result;
}