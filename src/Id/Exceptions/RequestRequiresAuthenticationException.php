<?php

namespace Anikeen\Id\Exceptions;

use Exception;

class RequestRequiresAuthenticationException extends Exception
{
    public function __construct($message = 'Request requires authentication', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}