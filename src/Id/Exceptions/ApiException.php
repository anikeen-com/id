<?php

namespace Anikeen\Id\Exceptions;

use Anikeen\Id\Result;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * A request to the ID service failed. `status` is the HTTP status (0 when the service could not
 * be reached), `error` the service's stable error code when it sent one (e.g. "already_cancelled").
 */
class ApiException extends ResourceException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $error = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * The exception for a failed request: Anikeen ID's message and error code, the HTTP status
     * (0 when there was no response).
     */
    public static function fromResult(Result $result): static
    {
        $body = is_object($result->data) ? $result->data : null;
        $status = $result->response()?->getStatusCode() ?? 0;

        // a failed conditional write; subclasses (CollectionException) keep their own type
        if ($status === 412 && static::class === self::class) {
            return PreconditionFailedException::fromResult($result);
        }

        return new static(
            rtrim((string) ($body->message ?? $result->error()), '.'),
            $status,
            $body->error ?? null,
            $result->exception,
        );
    }

    /**
     * The exception for a request that got no response (network error, timeout).
     */
    public static function unreachable(Throwable $previous): static
    {
        return new static(sprintf('Anikeen ID is not reachable: %s', $previous->getMessage()), 0, null, $previous);
    }

    /**
     * The JSON body of Anikeen ID's error response, null without one. Some errors carry details,
     * e.g. `net_amount` of "elapsed_amount_exceeded".
     */
    public function body(): ?object
    {
        $previous = $this->getPrevious();
        $response = $previous !== null && method_exists($previous, 'getResponse') ? $previous->getResponse() : null;

        if (!$response instanceof ResponseInterface) {
            return null;
        }

        $body = json_decode((string) $response->getBody());

        return is_object($body) ? $body : null;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    /**
     * The ID service could not be reached (network error, timeout) or failed (5xx): retry later.
     */
    public function isTemporary(): bool
    {
        return $this->status === 0 || $this->status >= 500 || $this->status === 429;
    }
}
