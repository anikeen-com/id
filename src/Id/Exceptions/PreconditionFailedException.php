<?php

namespace Anikeen\Id\Exceptions;

/**
 * A conditional write failed (412): the stored version wasn't the one sent with `If-Match`
 * ("preference_version_mismatch"), or the key was already set and the write was create-only
 * ("preference_exists"). Reload, merge and retry with currentVersion().
 */
class PreconditionFailedException extends ApiException
{
    /**
     * The version stored now, null when the key isn't set (or Anikeen ID didn't send one).
     */
    public function currentVersion(): ?int
    {
        $version = $this->body()->current_version ?? null;

        return is_numeric($version) ? (int) $version : null;
    }
}
