<?php

namespace Anikeen\Id\Exceptions;

/**
 * A list request failed. Like ApiException it carries the HTTP status (also as getCode()) and
 * Anikeen ID's error code.
 */
class CollectionException extends ApiException
{
}
