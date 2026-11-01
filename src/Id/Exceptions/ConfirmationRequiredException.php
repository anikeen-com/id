<?php

namespace Anikeen\Id\Exceptions;

use Throwable;

/**
 * Thrown when a third-party app tries to check out an order or subscription itself: the user has
 * to confirm it on Anikeen ID. Send them to the confirmation URL instead.
 */
class ConfirmationRequiredException extends ResourceException
{
    public function __construct(string $message, int $code, public readonly ?string $confirmationUrl = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
