<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Enums\SharedPreference;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\PreconditionFailedException;
use Anikeen\Id\Resources\Preference;
use Anikeen\Id\Resources\Preferences;
use Anikeen\Id\Support\PreferenceRequest;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The user's shared preferences (`global`, e.g. SharedPreference::APPEARANCE), with the user's
 * token (scope `preferences` or `preferences:read`). User tokens reach `global` only; an app's
 * own namespace needs the app token (AnikeenId::preferencesByUserId()).
 */
trait ManagesUserPreferences
{
    /**
     * The user's shared preferences, sorted by key. Cached on this instance; set and delete clear it.
     *
     * @throws ApiException
     */
    public function globalPreferences(): Preferences
    {
        $namespace = SharedPreference::NAMESPACE;

        if (!isset($this->preferencesCache[$namespace])) {
            $anikeenId = $this->anikeenId();

            $this->preferencesCache[$namespace] = Preferences::builder(
                fn () => $anikeenId->get(PreferenceRequest::path('v1/preferences', $namespace))
            );
        }

        return $this->preferencesCache[$namespace];
    }

    /**
     * The value of a shared preference, or $default when the user hasn't set it.
     *
     * @throws ApiException
     */
    public function globalPreference(string $key, mixed $default = null): mixed
    {
        return $this->globalPreferences()->get($key, $default);
    }

    /**
     * Set a shared preference of the user, e.g. setGlobalPreference(SharedPreference::APPEARANCE,
     * Appearance::DARK). Only registered keys and values are accepted (422 "unknown_shared_key",
     * "invalid_shared_value").
     *
     * @param int|null $ifVersion only if the stored version is this one (`If-Match`)
     * @throws PreconditionFailedException 412 "preference_version_mismatch", with currentVersion()
     * @throws ApiException
     */
    public function setGlobalPreference(string $key, mixed $value, ?int $ifVersion = null): Preference
    {
        $namespace = SharedPreference::NAMESPACE;
        $headers = PreferenceRequest::conditions($ifVersion);
        $anikeenId = $this->anikeenId();

        try {
            return new Preference(fn () => $anikeenId->put(PreferenceRequest::path('v1/preferences', $namespace, $key), ['value' => $value], [], null, $headers));
        } finally {
            unset($this->preferencesCache[$namespace]);
        }
    }

    /**
     * Delete a shared preference of the user.
     *
     * @param int|null $ifVersion only if the stored version is this one (`If-Match`)
     * @throws PreconditionFailedException 412 "preference_version_mismatch", with currentVersion()
     * @throws ApiException 404 "preference_not_found" when the key isn't set, …
     */
    public function deleteGlobalPreference(string $key, ?int $ifVersion = null): void
    {
        $namespace = SharedPreference::NAMESPACE;
        $headers = PreferenceRequest::conditions($ifVersion);

        try {
            $result = $this->anikeenId()->delete(PreferenceRequest::path('v1/preferences', $namespace, $key), [], [], null, $headers);
        } catch (GuzzleException $e) {
            throw ApiException::unreachable($e);
        } finally {
            unset($this->preferencesCache[$namespace]);
        }

        if (!$result->success()) {
            throw ApiException::fromResult($result);
        }
    }
}
