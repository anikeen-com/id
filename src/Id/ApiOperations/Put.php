<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;

trait Put
{
    abstract public function put(string $path = '', array $parameters = [], Paginator $paginator = null): Result;
}