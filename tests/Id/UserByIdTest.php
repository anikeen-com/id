<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Resources\User;
use Anikeen\Id\Webhooks\EventType;

class UserByIdTest extends TestCase
{
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

    public function test_find_user_by_id_reads_a_connected_user_with_the_app_token(): void
    {
        $this->respond(200, $this->profile());

        $user = app(AnikeenId::class)->findUserById('01JUSER');

        $this->assertSame('GET /api/v1/client/users/01JUSER Bearer app-token', $this->sentCall(0));
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('01JUSER', $user->id());
        $this->assertSame('Ada King', $user->name());
        $this->assertSame('ada', $user->username());
        $this->assertSame('ada@example.com', $user->email());
        $this->assertTrue($user->emailVerified());
        $this->assertSame('https://id.test/avatars/01JUSER', $user->avatar());
        $this->assertSame(8, $user->version());
        $this->assertSame('en', $user->locale);
        $this->assertNull($user->pendingEmail());
        $this->assertSame('Ada King', $user->jsonSerialize()['name']);
    }

    public function test_find_user_by_id_encodes_the_id(): void
    {
        $this->respond(200, $this->profile());

        app(AnikeenId::class)->findUserById('a/b');

        $this->assertSame('/api/v1/client/users/a%2Fb', $this->sent[0]['request']->getUri()->getPath());
    }

    public function test_find_user_by_id_returns_null_for_an_unknown_or_unconnected_user(): void
    {
        $this->respond(404, ['message' => 'User not found.', 'error' => 'user_not_found']);

        $this->assertNull(app(AnikeenId::class)->findUserById('01JOTHER'));
    }

    public function test_find_user_by_id_throws_other_failures(): void
    {
        $this->respond(403, ['message' => 'Only trusted apps.', 'error' => 'client_not_allowed']);

        try {
            app(AnikeenId::class)->findUserById('01JUSER');
            $this->fail('No exception thrown');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('client_not_allowed', $e->error);
        }

        // a 404 without Anikeen ID's code (e.g. an older release without the route) isn't "no user"
        $this->respond(404, ['message' => 'Not Found']);
        $this->expectException(ApiException::class);
        app(AnikeenId::class)->findUserById('01JUSER');
    }

    public function test_an_unverified_address_is_not_verified(): void
    {
        $this->respond(200, $this->profile(['email_verified_at' => null]));

        $this->assertFalse(app(AnikeenId::class)->findUserById('01JUSER')->emailVerified());
    }

    public function test_a_user_updated_webhook_object_is_a_user(): void
    {
        $user = new User(['object' => 'user', 'id' => '01JUSER', 'name' => 'Ada King', 'username' => 'ada',
            'email' => null, 'email_verified' => false, 'avatar' => 'https://id.test/avatars/01JUSER', 'version' => 9]);

        $this->assertSame(9, $user->version());
        $this->assertFalse($user->emailVerified());
        $this->assertNull($user->email());
        $this->assertSame('user', $user->object);
        $this->assertSame('user.updated', EventType::USER_UPDATED);
    }
}
