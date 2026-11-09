<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\ValidationFailedException;
use DateTimeImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;

class UserProfileTest extends TestCase
{
    private function owner(): Model
    {
        $owner = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $owner->anikeen_id = '01JUSER';
        $owner->anikeen_id_access_token = 'user-token';

        return $owner;
    }

    private function sentCall(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    private function profile(array $overrides = []): array
    {
        return ['data' => [
            'id' => '01JUSER', 'name' => 'Ada King', 'username' => 'ada', 'email' => 'ada@example.com',
            'email_verified_at' => '2026-11-01T10:00:00.000000Z', 'avatar' => 'https://id.test/avatars/01JUSER',
            'locale' => 'en', 'profile_version' => 8, 'created_at' => '2026-01-01T00:00:00.000000Z',
            ...$overrides,
        ]];
    }

    private function validationError(array $codes): array
    {
        return [
            'message' => 'The given data was invalid.',
            'error' => array_values($codes)[0],
            'errors' => array_map(fn () => ['The value is invalid.'], $codes),
            'codes' => $codes,
        ];
    }

    public function test_update_profile_patches_the_fields_with_the_user_token(): void
    {
        $this->respond(200, $this->profile(['name' => 'Ada Lovelace', 'profile_version' => 9,
            'pending_email' => 'new@example.com', 'pending_email_expires_at' => '2026-11-07T11:00:00.000000Z']));

        $user = $this->owner()->updateProfile(['name' => 'Ada Lovelace', 'email' => 'new@example.com']);

        $this->assertSame('PATCH /api/v1/user/profile Bearer user-token', $this->sentCall(0));
        $this->assertSame(['name' => 'Ada Lovelace', 'email' => 'new@example.com'], $this->sentJson(0));
        $this->assertSame('Ada Lovelace', $user->name());
        $this->assertSame('ada@example.com', $user->email());
        $this->assertSame('new@example.com', $user->pendingEmail());
        $this->assertEquals(new DateTimeImmutable('2026-11-07T11:00:00Z'), $user->pendingEmailExpiresAt());
        $this->assertSame(9, $user->version());
    }

    public function test_update_profile_clears_the_cached_user_data(): void
    {
        $owner = $this->owner();
        $this->respond(200, $this->profile());
        $this->respond(200, $this->profile(['name' => 'Ada Lovelace']));
        $this->respond(200, $this->profile(['name' => 'Ada Lovelace']));

        $this->assertSame('Ada King', $owner->getUserData()->name);
        $owner->updateProfile(['name' => 'Ada Lovelace']);

        $this->assertSame('Ada Lovelace', $owner->getUserData()->name);
        $this->assertCount(3, $this->sent);
        $this->assertSame('GET /api/v1/user Bearer user-token', $this->sentCall(2));
    }

    public function test_refused_profile_changes_carry_codes_per_field(): void
    {
        $this->respond(422, $this->validationError(['username' => 'username_taken', 'email' => 'email_invalid']));

        try {
            $this->owner()->updateProfile(['username' => 'taken', 'email' => 'nope']);
            $this->fail('No exception thrown');
        } catch (ValidationFailedException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('username_taken', $e->error);
            $this->assertSame(['username' => 'username_taken', 'email' => 'email_invalid'], $e->errorCodes());
            $this->assertSame('email_invalid', $e->errorCodeFor('email'));
            $this->assertNull($e->errorCodeFor('name'));
            $this->assertTrue($e->hasErrorCode('email_invalid'));
            $this->assertFalse($e->hasErrorCode('email_taken'));
            $this->assertSame(['The value is invalid.'], $e->errors()['username']);
            $this->assertInstanceOf(ApiException::class, $e);
        }
    }

    public function test_a_client_credentials_token_is_refused(): void
    {
        $this->respond(403, ['message' => 'This endpoint needs an access token of the user.', 'error' => 'user_token_required']);

        try {
            $this->owner()->updateProfile(['name' => 'Ada']);
            $this->fail('No exception thrown');
        } catch (ApiException $e) {
            $this->assertNotInstanceOf(ValidationFailedException::class, $e);
            $this->assertSame('user_token_required', $e->error);
        }
    }

    public function test_an_unreachable_service_is_an_api_exception(): void
    {
        $this->http->append(new ConnectException('Connection refused', new Request('PATCH', 'v1/user/profile')));

        try {
            $this->owner()->updateProfile(['name' => 'Ada']);
            $this->fail('No exception thrown');
        } catch (ApiException $e) {
            $this->assertSame(0, $e->status);
            $this->assertTrue($e->isTemporary());
        }
    }

    public function test_upload_avatar_sends_the_picture_as_multipart(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, 'picture-bytes');
        $this->respond(200, $this->profile(['profile_version' => 10]));

        try {
            $user = $this->owner()->uploadAvatar($path, ['x' => 10, 'y' => 20, 'size' => 300]);
        } finally {
            unlink($path);
        }

        $request = $this->sent[0]['request'];
        $body = (string) $request->getBody();

        $this->assertSame('POST /api/v1/user/profile/avatar Bearer user-token', $this->sentCall(0));
        $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('name="avatar"; filename="' . basename($path) . '"', $body);
        $this->assertStringContainsString('picture-bytes', $body);
        // Guzzle 7 adds a Content-Length per part, Guzzle 8 does not
        $this->assertMatchesRegularExpression('/name="crop\\[x\\]"\r\n(Content-Length: 2\r\n)?\r\n10\r\n/', $body);
        $this->assertStringContainsString('name="crop[y]"', $body);
        $this->assertStringContainsString('name="crop[size]"', $body);
        $this->assertSame(10, $user->version());
    }

    public function test_upload_avatar_without_crop_sends_only_the_picture(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, 'picture-bytes');
        $this->respond(200, $this->profile());

        try {
            $this->owner()->uploadAvatar(new \SplFileInfo($path));
        } finally {
            unlink($path);
        }

        $body = (string) $this->sent[0]['request']->getBody();
        $this->assertStringContainsString('name="avatar"', $body);
        $this->assertStringNotContainsString('crop', $body);
    }

    public function test_a_refused_upload_carries_its_code(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, 'not a picture');
        $this->respond(422, $this->validationError(['avatar' => 'avatar_invalid']));
        $this->respond(413, ['message' => 'The picture is too large.', 'error' => 'avatar_too_large']);

        try {
            try {
                $this->owner()->uploadAvatar($path);
                $this->fail('No exception thrown');
            } catch (ValidationFailedException $e) {
                $this->assertSame('avatar_invalid', $e->errorCodeFor('avatar'));
            }

            try {
                $this->owner()->uploadAvatar($path);
                $this->fail('No exception thrown');
            } catch (ApiException $e) {
                $this->assertSame(413, $e->status);
                $this->assertSame('avatar_too_large', $e->error);
            }
        } finally {
            unlink($path);
        }
    }

    public function test_crop_codes_are_found_under_the_crop_field(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, 'a picture');
        $this->respond(422, $this->validationError(['crop.size' => 'crop_invalid']));

        try {
            $this->owner()->uploadAvatar($path, ['x' => 0, 'y' => 0]);
            $this->fail('No exception thrown');
        } catch (ValidationFailedException $e) {
            $this->assertSame('crop_invalid', $e->errorCodeFor('crop'));
            $this->assertSame('crop_invalid', $e->errorCodeFor('crop.size'));
            $this->assertNull($e->errorCodeFor('cro'));
            $this->assertNull($e->errorCodeFor('avatar'));
        } finally {
            unlink($path);
        }
    }

    public function test_an_unreadable_file_throws_before_anything_is_sent(): void
    {
        try {
            $this->owner()->uploadAvatar(sys_get_temp_dir() . '/missing-avatar-' . uniqid() . '.png');
            $this->fail('No exception thrown');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(ApiException::class, $e);
            $this->assertCount(0, $this->sent);
        }
    }

    public function test_remove_avatar_deletes_the_upload_and_clears_the_cache(): void
    {
        $owner = $this->owner();
        $this->respond(200, $this->profile());
        $this->respond(200, $this->profile(['profile_version' => 11]));
        $this->respond(200, $this->profile(['profile_version' => 11]));

        $owner->getUserData();
        $user = $owner->removeAvatar();
        $owner->getUserData();

        $this->assertSame('DELETE /api/v1/user/profile/avatar Bearer user-token', $this->sentCall(1));
        $this->assertSame(11, $user->version());
        $this->assertCount(3, $this->sent);
    }
}
