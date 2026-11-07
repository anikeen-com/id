<?php

namespace Anikeen\Id\Resources;

use DateTimeImmutable;
use JsonSerializable;

/**
 * A user's profile: AnikeenId::findUserById(), the result of the profile updates on Billable
 * (updateProfile(), uploadAvatar(), removeAvatar()) and the `data.object` of a user.updated
 * webhook (`new User($event['data']['object'])`).
 *
 * The REST shapes carry `email_verified_at` and `profile_version`, the webhook `email_verified`
 * and `version`; emailVerified() and version() read either. The profile updates also return the
 * other fields of `GET v1/user` plus `pending_email` and `pending_email_expires_at`.
 *
 * @property string $id
 * @property ?string $name
 * @property ?string $username
 * @property ?string $email in a webhook only once verified (null while it isn't)
 * @property ?string $email_verified_at REST shapes
 * @property bool $email_verified webhook
 * @property ?string $avatar a stable URL; the picture behind it can change
 * @property ?string $locale REST shapes
 * @property int $profile_version REST shapes
 * @property int $version webhook
 * @property ?string $created_at REST shapes
 * @property ?string $pending_email profile updates only: a new address waiting for confirmation
 * @property ?string $pending_email_expires_at profile updates only
 */
class User implements JsonSerializable
{
    protected object $data;

    public function __construct(object|array $data)
    {
        $this->data = (object) $data;
    }

    public function __get(string $name): mixed
    {
        return $this->data->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data->{$name});
    }

    public function id(): string
    {
        return (string) ($this->data->id ?? '');
    }

    public function name(): ?string
    {
        return $this->data->name ?? null;
    }

    public function username(): ?string
    {
        return $this->data->username ?? null;
    }

    public function email(): ?string
    {
        return $this->data->email ?? null;
    }

    /**
     * From `email_verified` (webhooks) or `email_verified_at` (REST shapes).
     */
    public function emailVerified(): bool
    {
        if (isset($this->data->email_verified)) {
            return (bool) $this->data->email_verified;
        }

        return !empty($this->data->email_verified_at);
    }

    /**
     * The URL of the profile picture. It stays the same when the picture changes (user.updated
     * with `avatar` in `changed`): drop a cached copy then.
     */
    public function avatar(): ?string
    {
        return $this->data->avatar ?? null;
    }

    /**
     * Grows with every change apps see (`profile_version` in REST shapes, `version` in webhooks):
     * keep the highest one seen and ignore older data.
     */
    public function version(): int
    {
        return (int) ($this->data->profile_version ?? $this->data->version ?? 0);
    }

    /**
     * A new email address waiting for the user's confirmation (profile updates only); `email`
     * stays the current address until then.
     */
    public function pendingEmail(): ?string
    {
        return $this->data->pending_email ?? null;
    }

    public function pendingEmailExpiresAt(): ?DateTimeImmutable
    {
        return !empty($this->data->pending_email_expires_at) ? new DateTimeImmutable($this->data->pending_email_expires_at) : null;
    }

    public function toArray(): array
    {
        return (array) $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
