<?php

namespace Anikeen\Id\Resources;

/**
 * A preference of a user: a JSON value under a namespace and a key. `version` is opaque: it only
 * goes up (per user, not consecutive per key) and is what ifVersion (`If-Match`) compares.
 * JSON objects come back as stdClass, so `{}` stays an object.
 *
 * @property string $namespace
 * @property string $key
 * @property mixed $value
 * @property int $version
 * @property string $updated_at
 * @property object{type: string, client_id: ?string} $updated_by `type` "user" (written with a user token) or "app"
 */
class Preference extends BaseResource
{
    /**
     * The write that returned this preference created the key (201). False for reads, updates and
     * writes of the value already stored.
     */
    public function wasCreated(): bool
    {
        return $this->result->response()?->getStatusCode() === 201;
    }
}
