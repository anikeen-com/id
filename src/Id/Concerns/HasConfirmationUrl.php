<?php

namespace Anikeen\Id\Concerns;

/**
 * Orders and subscriptions created by third-party apps are confirmed by the user on Anikeen ID.
 */
trait HasConfirmationUrl
{
    /**
     * Where the user confirms (or cancels) this booking, or null once it was checked out.
     *
     * Afterwards the user is sent back to $returnUrl with `status` (completed, pending or
     * cancelled) and `order` or `subscription`. The return URL must have the origin of one of
     * the app's redirect URIs. Check the status through the API; don't trust the query string.
     */
    public function confirmationUrl(?string $returnUrl = null): ?string
    {
        $url = $this->confirmation_url ?? null;

        if (!$url || !$returnUrl) {
            return $url ?: null;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['return_url' => $returnUrl]);
    }
}
