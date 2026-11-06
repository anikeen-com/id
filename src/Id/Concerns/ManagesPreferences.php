<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\PreconditionFailedException;
use Anikeen\Id\Resources\Preference;
use Anikeen\Id\Resources\Preferences;
use Anikeen\Id\Support\AppRequest;
use Anikeen\Id\Support\PreferenceRequest;

/**
 * Preferences of any user, with the app's own token (client credentials, scope
 * `preferences:client`). Anikeen has to register the app for each namespace it uses: its own
 * namespaces (read, write, lookup), read access to another app's namespace, or access to `global`.
 * Without it every call fails with 403 "client_not_trusted" or 404 "namespace_not_found".
 */
trait ManagesPreferences
{
    /**
     * All preferences of a user in a namespace, sorted by key.
     *
     * @throws ApiException 404 "namespace_not_found", "user_not_found" (also deleted users), …
     */
    public function preferencesByUserId(string $userId, string $namespace): Preferences
    {
        return Preferences::builder(fn () => AppRequest::send('GET', $this->userPreferencePath($userId, $namespace)));
    }

    /**
     * One preference of a user.
     *
     * @throws ApiException 404 "preference_not_found" when the key isn't set, "user_not_found", …
     */
    public function preferenceByUserId(string $userId, string $namespace, string $key): Preference
    {
        return new Preference(fn () => AppRequest::send('GET', $this->userPreferencePath($userId, $namespace, $key)));
    }

    /**
     * Set a preference of a user. The value is any JSON value (pass `(object) []` for `{}`), at
     * most 2048 bytes and 4 levels deep. Writing the value already stored changes nothing and
     * succeeds, also when a precondition would fail, so a retried write is safe.
     *
     * @param int|null $ifVersion only if the stored version is this one (`If-Match`)
     * @param bool $createOnly only if the key isn't set yet (`If-None-Match: *`); not with $ifVersion
     * @throws PreconditionFailedException 412 "preference_version_mismatch" / "preference_exists",
     *     with currentVersion()
     * @throws ApiException 403 "namespace_read_only", 409 "preference_value_taken" (a unique key),
     *     422 "invalid_key", "forbidden_key", "value_too_large", "preference_limit_exceeded", …
     */
    public function setPreferenceByUserId(string $userId, string $namespace, string $key, mixed $value, ?int $ifVersion = null, bool $createOnly = false): Preference
    {
        $headers = PreferenceRequest::conditions($ifVersion, $createOnly);

        return new Preference(fn () => AppRequest::send('PUT', $this->userPreferencePath($userId, $namespace, $key), ['value' => $value], [], $headers));
    }

    /**
     * Delete a preference of a user.
     *
     * @param int|null $ifVersion only if the stored version is this one (`If-Match`)
     * @throws PreconditionFailedException 412 "preference_version_mismatch", with currentVersion()
     * @throws ApiException 404 "preference_not_found" when the key isn't set, …
     */
    public function deletePreferenceByUserId(string $userId, string $namespace, string $key, ?int $ifVersion = null): void
    {
        AppRequest::send('DELETE', $this->userPreferencePath($userId, $namespace, $key), null, [], PreferenceRequest::conditions($ifVersion));
    }

    /**
     * The ID of the user holding a value of a unique key in one of the app's own namespaces, e.g.
     * the user of a payment provider's customer ID. The value is sent in the body, never in the URL.
     *
     * @return string|null null when no (not deleted) user holds the value
     * @throws ApiException 422 "key_not_unique", "invalid_unique_value", 403 "lookup_not_allowed", …
     */
    public function findUserIdByPreference(string $namespace, string $key, string $value): ?string
    {
        try {
            $result = AppRequest::send('POST', PreferenceRequest::path('v1/client/preferences', $namespace, 'lookup'), [
                'key' => $key,
                'value' => $value,
            ]);
        } catch (ApiException $e) {
            // only Anikeen ID's own answer means "nobody holds it", not e.g. an unknown namespace
            if ($e->isNotFound() && $e->error === 'preference_not_found') {
                return null;
            }

            throw $e;
        }

        return $result->data->user_id;
    }

    private function userPreferencePath(string $userId, string $namespace, ?string $key = null): string
    {
        $segments = [$userId, 'preferences', $namespace];

        if ($key !== null) {
            $segments[] = $key;
        }

        return PreferenceRequest::path('v1/client/users', ...$segments);
    }
}
