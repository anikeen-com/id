<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Enums\Role;
use Anikeen\Id\Resources\User;
use Anikeen\Id\Socialite\Provider;
use Illuminate\Http\Request;
use ReflectionMethod;

/**
 * The user's role, seen by trusted apps only: at sign-in, read by ID and in user.updated.
 */
class UserRoleTest extends TestCase
{
    private function socialiteUser(array $raw): \SocialiteProviders\Manager\OAuth2\User
    {
        $provider = new Provider(Request::create('/'), 'client', 'secret', 'https://app.test/callback');
        $map = new ReflectionMethod($provider, 'mapUserToObject');

        return $map->invoke($provider, $raw);
    }

    public function test_the_socialite_user_carries_the_role(): void
    {
        $raw = ['id' => '01JUSER', 'username' => 'ada', 'name' => 'Ada', 'email' => 'ada@example.com', 'avatar' => null];

        $this->assertSame(Role::ADMIN, $this->socialiteUser([...$raw, 'role' => 'admin'])->role);
        // apps that aren't trusted never get it
        $this->assertNull($this->socialiteUser($raw)->role);
    }

    public function test_a_user_read_by_id_has_the_role(): void
    {
        $this->respond(200, ['data' => ['id' => '01JUSER', 'name' => 'Ada', 'role' => 'admin']]);

        $user = app(AnikeenId::class)->findUserById('01JUSER');

        $this->assertSame(Role::ADMIN, $user->role());
        $this->assertTrue($user->isAdmin());
    }

    public function test_the_webhook_object_has_it_for_trusted_apps_only(): void
    {
        $trusted = new User(['object' => 'user', 'id' => '01JUSER', 'version' => 3, 'role' => 'user']);
        $other = new User(['object' => 'user', 'id' => '01JUSER', 'version' => 3]);

        $this->assertSame(Role::USER, $trusted->role());
        $this->assertFalse($trusted->isAdmin());
        $this->assertNull($other->role());
        $this->assertFalse($other->isAdmin());
        $this->assertSame([Role::ADMIN, Role::USER], Role::all());
    }
}
