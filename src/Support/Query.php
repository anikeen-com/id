<?php

namespace Anikeen\Support;

class Query
{
    /**
     * Build query with support for multiple same first-dimension keys.
     *
     * @param array $query
     *
     * @return string
     */
    public static function build(array $query): string
    {
        $parts = [];
        foreach ($query as $name => $value) {
            $value = (array)$value;
            array_walk_recursive($value, function ($value) use (&$parts, $name) {
                $parts[] = urlencode($name) . '=' . urlencode($value);
            });
        }

        return implode('&', $parts);
    }
}