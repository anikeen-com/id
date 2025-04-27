<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Helpers\Paginator;
use Anikeen\Id\Result;

trait Delete
{
    abstract public function delete(string $path = '', array $parameters = [], Paginator $paginator = null): Result;
}