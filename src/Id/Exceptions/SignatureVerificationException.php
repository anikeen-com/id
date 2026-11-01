<?php

namespace Anikeen\Id\Exceptions;

use Exception;

/**
 * The webhook request was not signed by Anikeen ID with your endpoint's secret, or is too old.
 */
class SignatureVerificationException extends Exception
{
    //
}
