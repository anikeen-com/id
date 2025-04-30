<?php

namespace Anikeen\Id\Concerns;

use stdClass;

trait MagicProperties
{
    protected function setMagicProperties(stdClass $data): void
    {
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}