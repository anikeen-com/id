<?php

namespace Anikeen\Id\ApiOperations;

use Anikeen\Id\Exceptions\RequestRequiresMissingParametersException;
use Illuminate\Support\Arr;

trait Validation
{
    /**
     * @throws RequestRequiresMissingParametersException
     */
    public function validateRequired(array $parameters, array $required)
    {
        if (!Arr::has($parameters, $required)) {
            throw RequestRequiresMissingParametersException::fromValidateRequired($parameters, $required);
        }
    }
}