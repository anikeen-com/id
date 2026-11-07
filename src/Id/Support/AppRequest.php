<?php

namespace Anikeen\Id\Support;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Contracts\AppTokenRepository;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\PreconditionFailedException;
use Anikeen\Id\Exceptions\ValidationFailedException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

/**
 * Sends a request with the app's own token (client credentials). A rejected token is renewed
 * once; every failure ends in an ApiException with status and error code (a 412 in a
 * PreconditionFailedException, a 422 in a ValidationFailedException).
 */
final class AppRequest
{
    /**
     * @param array<string, string> $headers Extra request headers, e.g. `If-Match`.
     * @throws ApiException
     */
    public static function send(string $method, string $path, ?array $payload = null, array $query = [], array $headers = []): Result
    {
        $result = self::attempt($method, $path, $payload, $query, $headers);

        if ($result->status === 401) {
            $tokens = app(AppTokenRepository::class);

            if (method_exists($tokens, 'forgetAccessToken')) {
                $tokens->forgetAccessToken();
                $result = self::attempt($method, $path, $payload, $query, $headers);
            }
        }

        if (!$result->success()) {
            $body = is_object($result->data) ? $result->data : null;
            $exception = match ($result->status) {
                412 => PreconditionFailedException::class,
                422 => ValidationFailedException::class,
                default => ApiException::class,
            };

            throw new $exception(
                sprintf('Anikeen ID request %s %s failed: %s', $method, $path, $body->message ?? $result->error()),
                $result->response()?->getStatusCode() ?? 0,
                $body->error ?? null,
                $result->exception,
            );
        }

        return $result;
    }

    /**
     * @throws ApiException
     */
    private static function attempt(string $method, string $path, ?array $payload, array $query, array $headers = []): Result
    {
        try {
            return app(AnikeenId::class)->asApp()->request($method, $path, $payload, $query, null, false, $headers);
        } catch (ApiException $e) {
            throw $e;
        } catch (GuzzleException|Throwable $e) {
            // network errors / timeouts (ConnectException isn't a RequestException) and token errors
            throw new ApiException(sprintf('Anikeen ID is not reachable: %s', $e->getMessage()), 0, null, $e);
        }
    }
}
