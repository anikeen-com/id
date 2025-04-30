<?php

namespace Anikeen\Id\Concerns;

use stdClass;

trait MagicProperties
{
    protected function setMagicProperties(stdClass|array $data): void
    {
        foreach ((object)$data as $key => $value) {
            if (!property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Magic getter: return null for undefined properties
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return null;
    }

    /**
     * Magic isset: return false for undefined properties
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return false;
    }
}