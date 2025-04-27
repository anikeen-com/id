<?php

namespace Anikeen\Id\Exceptions;

use Exception;

class RequestRequiresMissingParametersException extends Exception
{
    public function __construct($message = 'Request requires missing parameters', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function fromValidateRequired(array $given, array $required): RequestRequiresMissingParametersException
    {
        return new self(sprintf(
            'Request requires missing parameters. Required: %s. Given: %s',
            implode(', ', $required),
            implode(', ', $given)
        ));
    }
}