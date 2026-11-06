<?php

namespace Anikeen\Id\Support;

use InvalidArgumentException;

/**
 * @internal paths and preconditions of the preferences API
 */
final class PreferenceRequest
{
    /**
     * The path with every segment URL-encoded, e.g. path('v1/preferences', 'global', 'appearance').
     */
    public static function path(string $base, string ...$segments): string
    {
        return $base . '/' . implode('/', array_map('rawurlencode', $segments));
    }

    /**
     * `If-Match: "<version>"` (only if the stored version is this one) or `If-None-Match: *`
     * (only if the key isn't set yet).
     *
     * @return array<string, string>
     */
    public static function conditions(?int $ifVersion, bool $createOnly = false): array
    {
        if ($ifVersion !== null && $createOnly) {
            throw new InvalidArgumentException('A write is either conditional on a version or create-only, not both.');
        }

        return match (true) {
            $ifVersion !== null => ['If-Match' => sprintf('"%d"', $ifVersion)],
            $createOnly => ['If-None-Match' => '*'],
            default => [],
        };
    }

    private function __construct()
    {
    }
}
