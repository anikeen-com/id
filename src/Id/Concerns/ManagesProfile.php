<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\ValidationFailedException;
use Anikeen\Id\Resources\User;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use SplFileInfo;
use Throwable;

/**
 * The user's own profile, with the user's token. Changes need the scope `user` (`user:read`
 * only reads; 403 "missing_scope").
 */
trait ManagesProfile
{
    use HasBillable;

    /**
     * Get the profile url for the current user.
     *
     * @param string|null $returnUrl The URL to redirect to after the user has completed their profile.
     * @param array $options Additional options for the profile URL.
     * @return string
     * @throws Throwable
     */
    public function profilePortalUrl(?string $returnUrl = null, array $options = []): string
    {
        return $this->anikeenId()->request('POST', 'v1/user/profile', [
            'return_url' => $returnUrl,
            'options' => $options,
        ])->data->url;
    }

    /**
     * Change the user's profile (scope `user`). Only the fields sent change. A new email address
     * doesn't replace the current one: Anikeen ID mails a confirmation link to it, and it becomes
     * the user's address once they confirm it signed in to Anikeen ID (see pendingEmail() of the
     * result; user.updated follows then). Clears the cached getUserData().
     *
     * @param array{
     *       name?: string,
     *       username?: string,
     *       email?: string
     *   } $attributes The fields to change
     *     - name:     The display name
     *     - username: The username (unique regardless of case)
     *     - email:    A new email address (lower-cased; needs the user's confirmation)
     * @return User the user as `GET v1/user` returns them, plus `pending_email` and
     *     `pending_email_expires_at`
     * @throws ValidationFailedException 422 with the codes "name_invalid", "username_taken",
     *     "username_invalid", "email_taken", "email_invalid" (errorCodeFor($field))
     * @throws ApiException 401 "unauthenticated", 403 "missing_scope", "user_token_required",
     *     429 "too_many_requests" (30 a minute per app and user, new email addresses 5 an hour)
     */
    public function updateProfile(array $attributes): User
    {
        $anikeenId = $this->anikeenId();

        return $this->sendProfileRequest(fn () => $anikeenId->request('PATCH', 'v1/user/profile', $attributes));
    }

    /**
     * Upload a new profile picture (scope `user`): jpeg, png, webp or gif, at most 5 MB, 64 to
     * 8000 pixels per side. The avatar URL stays the same, the picture behind it changes. Clears
     * the cached getUserData().
     *
     * @param string|SplFileInfo $file a path or a file, e.g. an UploadedFile
     * @param array{x: int, y: int, size: int}|null $crop the square to use, in pixels of the
     *     upright picture; the centre without one
     * @throws ValidationFailedException 422 with the codes "avatar_invalid", "avatar_too_large",
     *     "crop_invalid"
     * @throws ApiException 413 "avatar_too_large", 401, 403 "missing_scope", "user_token_required",
     *     429 "too_many_requests" (also more than 10 uploads an hour)
     */
    public function uploadAvatar(string|SplFileInfo $file, ?array $crop = null): User
    {
        $path = $file instanceof SplFileInfo ? $file->getPathname() : $file;
        $filename = $file instanceof SplFileInfo && method_exists($file, 'getClientOriginalName')
            ? $file->getClientOriginalName()
            : basename($path);

        $multipart = [['name' => 'avatar', 'contents' => Utils::tryFopen($path, 'r'), 'filename' => $filename]];

        foreach (['x', 'y', 'size'] as $key) {
            if (isset($crop[$key])) {
                $multipart[] = ['name' => "crop[{$key}]", 'contents' => (string) $crop[$key]];
            }
        }

        $anikeenId = $this->anikeenId();

        return $this->sendProfileRequest(fn () => $anikeenId->requestMultipart('POST', 'v1/user/profile/avatar', $multipart));
    }

    /**
     * Remove the uploaded profile picture (scope `user`); the initials are shown again when it
     * was the one shown. Clears the cached getUserData().
     *
     * @throws ApiException 401, 403 "missing_scope", "user_token_required", 429 "too_many_requests"
     */
    public function removeAvatar(): User
    {
        $anikeenId = $this->anikeenId();

        return $this->sendProfileRequest(fn () => $anikeenId->request('DELETE', 'v1/user/profile/avatar'));
    }

    /**
     * @param callable(): Result $send
     * @throws ApiException
     */
    private function sendProfileRequest(callable $send): User
    {
        try {
            $result = $send();
        } catch (GuzzleException $e) {
            throw ApiException::unreachable($e);
        } finally {
            $this->userDataCache = null;
        }

        if (!$result->success()) {
            throw ApiException::fromResult($result);
        }

        return new User($result->data);
    }
}