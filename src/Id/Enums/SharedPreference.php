<?php

namespace Anikeen\Id\Enums;

/**
 * The keys of the `global` preferences namespace, shared by all apps. Anikeen ID accepts only
 * these keys there, each with its own values.
 */
class SharedPreference
{
    /** The namespace of the shared preferences. */
    const NAMESPACE = 'global';

    /** Light or dark mode: one of the Appearance constants. */
    const APPEARANCE = 'appearance';
}
