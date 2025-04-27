<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;

trait Post
{
    abstract public function post(string $path = '', array $parameters = [], Paginator $paginator = null): Result;
}