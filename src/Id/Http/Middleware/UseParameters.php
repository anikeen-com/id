<?php

namespace Anikeen\Id\Http\Middleware;

abstract class UseParameters
{
    /**
     * Specify the parameters for the middleware.
     *
     * @param string[]|string $param
     */
    public static function using(array|string $param, string ...$params): string
    {
        if (is_array($param)) {
            return static::class . ':' . implode(',', $param);
        }

        return static::class . ':' . implode(',', [$param, ...$params]);
    }
}