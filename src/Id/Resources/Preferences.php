<?php

namespace Anikeen\Id\Resources;

/**
 * The preferences of a user in one namespace, sorted by key (at most 50, no pagination).
 */
class Preferences extends BaseCollection
{
    /**
     * The preference of a key; null when it isn't set. Reads the list, no request.
     */
    public function find(string $id): ?Preference
    {
        $data = $this->item($id);

        if ($data === null) {
            return null;
        }

        $result = clone $this->result;
        $result->data = $data;

        return new Preference(fn () => $result);
    }

    /**
     * The value of a key, or $default when it isn't set.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->item($key);

        return $data !== null ? $data->value : $default;
    }

    public function has(string $key): bool
    {
        return $this->item($key) !== null;
    }

    /**
     * The values by key.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        foreach ((array) $this->result->data as $data) {
            $values[$data->key] = $data->value;
        }

        return $values;
    }

    private function item(string $key): ?object
    {
        foreach ((array) $this->result->data as $data) {
            if (($data->key ?? null) === $key) {
                return $data;
            }
        }

        return null;
    }
}
