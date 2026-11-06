<?php

namespace Anikeen\Id\Enums;

/**
 * The values of the shared preference SharedPreference::APPEARANCE.
 */
class Appearance
{
    /** Follow the operating system. */
    const SYSTEM = 'system';
    const LIGHT = 'light';
    const DARK = 'dark';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SYSTEM, self::LIGHT, self::DARK];
    }
}
