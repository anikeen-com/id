<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\HasAnikeenTokens;
use Anikeen\Id\Helpers\JwtParser;
use Anikeen\Id\Http\Middleware\CheckForAnyScope;
use Anikeen\Id\Http\Middleware\CheckScopes;
use Firebase\JWT\JWT;
use Illuminate\Auth\RequestGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TokenGuardTest extends TestCase
{
    private static ?string $privateKey = null;

    private static string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$privateKey === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$privateKey = $private;
            self::$publicKey = openssl_pkey_get_details($key)['key'];
        }

        JwtParser::flushKeys();
        config([
            'services.anikeen.public_key' => self::$publicKey,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'database.default' => 'testing',
            'auth.guards.api' => ['driver' => 'anikeen', 'provider' => 'anikeen'],
            'auth.providers.anikeen' => ['driver' => 'anikeen', 'model' => GuardUser::class],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('anikeen_id')->nullable();
            $table->string('email');
        });

        GuardUser::query()->insert([
            ['id' => 1, 'anikeen_id' => 'ulid-of-alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'anikeen_id' => '1', 'email' => 'bob@example.com'],
        ]);

        Route::middleware('auth:api')->get('/me', fn () => ['email' => auth()->user()->email, 'scopes' => auth()->user()->anikeenToken()->scopes]);
        Route::middleware(['auth:api', CheckScopes::using('user')])->post('/write', fn () => ['ok' => true]);
        Route::middleware(['auth:api', CheckForAnyScope::using('user', 'user:read')])->get('/read', fn () => ['ok' => true]);
        Route::middleware('auth:api')->get('/client', fn () => [
            'id' => auth()->user()->anikeenTokenClientId(),
            'name' => auth()->user()->anikeenTokenClientName(),
            'trusted' => auth()->user()->anikeenTokenClientTrusted(),
            'personal_access' => auth()->user()->anikeenTokenIsPersonalAccess(),
            'attribute_name' => request()->attributes->get('oauth_client_name'),
            'attribute_trusted' => request()->attributes->get('oauth_client_trusted'),
        ]);
    }

    protected function tearDown(): void
    {
        AnikeenId::provisionUsersUsing(null);

        parent::tearDown();
    }

    /**
     * A provisioner like an app would register: creates the user from the profile.
     */
    private function provisionFromProfile(array &$calls): void
    {
        AnikeenId::provisionUsersUsing(function (stdClass $claims, array $profile, string $jwt) use (&$calls) {
            $calls[] = compact('claims', 'profile', 'jwt');

            return GuardUser::query()->create(['anikeen_id' => $claims->sub, 'email' => $profile['email']]);
        });
    }

    /**
     * Like a fresh request in production: the guard remembers its user per application.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        Auth::forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private function token(array $claims = [], ?string $privateKey = null): string
    {
        $now = time();

        return JWT::encode(array_merge([
            'aud' => '019a0000-0000-7000-8000-000000000001',
            'jti' => Str::random(40),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            'sub' => 'ulid-of-alice',
            'scopes' => ['user'],
        ], $claims), $privateKey ?? self::$privateKey, 'RS256');
    }

    public function test_the_api_guard_resolves(): void
    {
        $this->assertInstanceOf(RequestGuard::class, Auth::guard('api'));
        $this->assertNull(Auth::guard('api')->user());
    }

    public function test_a_valid_token_resolves_the_user_by_anikeen_id(): void
    {
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token()])
            ->assertOk()
            ->assertExactJson(['email' => 'alice@example.com', 'scopes' => ['user']]);
    }

    public function test_the_user_is_not_looked_up_by_primary_key(): void
    {
        // sub "2" is bob's primary key, but nobody has the Anikeen ID "2"
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => '2'])])->assertUnauthorized();
        // sub "1" is bob's Anikeen ID (alice's primary key)
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => '1'])])
            ->assertOk()->assertJsonPath('email', 'bob@example.com');
    }

    public function test_the_lookup_column_is_configurable(): void
    {
        config(['auth.providers.anikeen.column' => 'email']);

        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => 'bob@example.com'])])
            ->assertOk()->assertJsonPath('email', 'bob@example.com');
    }

    public function test_the_eloquent_provider_works_too(): void
    {
        config([
            'auth.guards.api.provider' => 'users',
            'auth.providers.users' => ['driver' => 'eloquent', 'model' => GuardUser::class],
        ]);

        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token()])
            ->assertOk()->assertJsonPath('email', 'alice@example.com');
    }

    public function test_invalid_tokens_answer_401_json(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPrivate);

        $cases = [
            'no token' => [],
            'garbage' => ['Authorization' => 'Bearer not-a-jwt'],
            'expired' => ['Authorization' => 'Bearer '.$this->token(['exp' => time() - 120])],
            'not yet valid' => ['Authorization' => 'Bearer '.$this->token(['nbf' => time() + 600])],
            'foreign key' => ['Authorization' => 'Bearer '.$this->token([], $otherPrivate)],
            'unknown user' => ['Authorization' => 'Bearer '.$this->token(['sub' => 'nobody'])],
            'client credentials' => ['Authorization' => 'Bearer '.$this->token(['sub' => ''])],
        ];

        foreach ($cases as $case => $headers) {
            $this->getJson('/me', $headers)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.'], $case);
        }
    }

    public function test_scopes_are_enforced(): void
    {
        $read = $this->token(['scopes' => ['user:read']]);
        $billing = $this->token(['scopes' => ['billing']]);
        $all = $this->token(['scopes' => ['*']]);

        $this->getJson('/read', ['Authorization' => "Bearer $read"])->assertOk();
        $this->postJson('/write', [], ['Authorization' => "Bearer $read"])->assertForbidden();
        $this->getJson('/read', ['Authorization' => "Bearer $billing"])->assertForbidden();
        $this->postJson('/write', [], ['Authorization' => "Bearer $all"])->assertOk();
        $this->postJson('/write', [], ['Authorization' => 'Bearer '.$this->token()])->assertOk();
    }

    public function test_token_can(): void
    {
        $user = GuardUser::query()->find(1)->withAnikeenAccessToken((object) ['scopes' => ['user:read']]);

        $this->assertTrue($user->anikeenTokenCan('user:read'));
        $this->assertFalse($user->anikeenTokenCan('user'));
        $this->assertTrue($user->anikeenTokenCanAny(['user', 'user:read']));
        $this->assertFalse((new GuardUser())->anikeenTokenCan('user'));
    }

    public function test_revoked_tokens_are_rejected_when_revocation_is_verified(): void
    {
        config(['services.anikeen.verify_revocation' => true]);
        $active = $this->token();
        $revoked = $this->token();

        $this->respond(200, ['data' => ['id' => 'ulid-of-alice']]);
        $this->getJson('/me', ['Authorization' => "Bearer $active"])->assertOk();
        // cached: no second request to Anikeen ID
        $this->getJson('/me', ['Authorization' => "Bearer $active"])->assertOk();
        $this->assertCount(1, $this->sent);
        $this->assertSame("Bearer $active", $this->sent[0]['request']->getHeaderLine('Authorization'));
        $this->assertStringEndsWith('v1/user', (string) $this->sent[0]['request']->getUri());

        $this->respond(401, ['message' => 'Unauthenticated.']);
        $this->getJson('/me', ['Authorization' => "Bearer $revoked"])->assertUnauthorized();

        // Anikeen ID down: fail closed, not cached
        $other = $this->token();
        $this->respond(503);
        $this->getJson('/me', ['Authorization' => "Bearer $other"])->assertUnauthorized();
        $this->respond(200, ['data' => ['id' => 'ulid-of-alice']]);
        $this->getJson('/me', ['Authorization' => "Bearer $other"])->assertOk();
    }

    public function test_an_unknown_user_is_provisioned_through_the_callback(): void
    {
        $calls = [];
        $this->provisionFromProfile($calls);
        $jwt = $this->token(['sub' => 'ulid-of-carol', 'scopes' => ['console-containers']]);

        $this->respond(200, ['data' => ['id' => 'ulid-of-carol', 'email' => 'carol@example.com', 'email_verified_at' => '2026-11-01T10:00:00Z']]);
        $this->getJson('/me', ['Authorization' => "Bearer $jwt"])
            ->assertOk()
            ->assertExactJson(['email' => 'carol@example.com', 'scopes' => ['console-containers']]);

        $this->assertCount(1, $calls);
        $this->assertSame('ulid-of-carol', $calls[0]['claims']->sub);
        $this->assertSame(['console-containers'], $calls[0]['claims']->scopes);
        $this->assertSame('carol@example.com', $calls[0]['profile']['email']);
        $this->assertSame('2026-11-01T10:00:00Z', $calls[0]['profile']['email_verified_at']);
        $this->assertSame($jwt, $calls[0]['jwt']);

        // the profile was read with the user's token
        $this->assertCount(1, $this->sent);
        $this->assertSame("Bearer $jwt", $this->sent[0]['request']->getHeaderLine('Authorization'));
        $this->assertStringEndsWith('v1/user', (string) $this->sent[0]['request']->getUri());

        // known from now on: no second call, no second request
        $this->getJson('/me', ['Authorization' => "Bearer $jwt"])->assertOk();
        $this->assertCount(1, $calls);
        $this->assertCount(1, $this->sent);
    }

    public function test_the_profile_of_the_revocation_check_is_reused(): void
    {
        config(['services.anikeen.verify_revocation' => true]);
        $calls = [];
        $this->provisionFromProfile($calls);

        $this->respond(200, ['data' => ['id' => 'ulid-of-carol', 'email' => 'carol@example.com']]);
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => 'ulid-of-carol'])])
            ->assertOk()->assertJsonPath('email', 'carol@example.com');

        $this->assertCount(1, $calls);
        $this->assertCount(1, $this->sent);
    }

    public function test_the_profile_is_read_again_when_the_revocation_check_was_cached(): void
    {
        config(['services.anikeen.verify_revocation' => true]);
        $jwt = $this->token(['sub' => 'ulid-of-carol']);

        // first request without a provisioner: active (cached), but unknown
        $this->respond(200, ['data' => ['id' => 'ulid-of-carol', 'email' => 'carol@example.com']]);
        $this->getJson('/me', ['Authorization' => "Bearer $jwt"])->assertUnauthorized();

        $calls = [];
        $this->provisionFromProfile($calls);
        $this->respond(200, ['data' => ['id' => 'ulid-of-carol', 'email' => 'carol@example.com']]);
        $this->getJson('/me', ['Authorization' => "Bearer $jwt"])->assertOk();

        $this->assertCount(1, $calls);
        $this->assertCount(2, $this->sent);
    }

    public function test_known_users_and_tokens_without_a_user_are_not_provisioned(): void
    {
        $calls = [];
        $this->provisionFromProfile($calls);

        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token()])->assertOk()->assertJsonPath('email', 'alice@example.com');
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => ''])])->assertUnauthorized();

        $this->assertSame([], $calls);
        $this->assertCount(0, $this->sent);
    }

    public function test_no_user_is_provisioned_without_a_profile_or_when_the_callback_declines(): void
    {
        $calls = [];
        $this->provisionFromProfile($calls);

        // Anikeen ID refuses the token or is down: the callback is not called
        $this->respond(401, ['message' => 'Unauthenticated.']);
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => 'ulid-of-carol'])])->assertUnauthorized();
        $this->respond(503);
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => 'ulid-of-carol'])])->assertUnauthorized();
        $this->assertSame([], $calls);

        // the callback declines
        AnikeenId::provisionUsersUsing(fn () => null);
        $this->respond(200, ['data' => ['id' => 'ulid-of-carol', 'email' => 'carol@example.com']]);
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => 'ulid-of-carol'])])->assertUnauthorized();
        $this->assertSame(0, GuardUser::query()->where('anikeen_id', 'ulid-of-carol')->count());
    }

    public function test_exceptions_of_the_callback_reach_the_app(): void
    {
        AnikeenId::provisionUsersUsing(fn () => throw new HttpException(409, 'The e-mail address belongs to another account.'));

        $this->respond(200, ['data' => ['id' => 'ulid-of-carol', 'email' => 'alice@example.com']]);
        $this->getJson('/me', ['Authorization' => 'Bearer '.$this->token(['sub' => 'ulid-of-carol'])])
            ->assertStatus(409)
            ->assertJsonPath('message', 'The e-mail address belongs to another account.');
    }

    public function test_the_client_claim_is_exposed(): void
    {
        $trusted = $this->token(['aud' => 'client-of-shop', 'client' => ['name' => 'Shop', 'trusted' => true]]);

        $this->getJson('/client', ['Authorization' => "Bearer $trusted"])->assertOk()->assertExactJson([
            'id' => 'client-of-shop',
            'name' => 'Shop',
            'trusted' => true,
            'personal_access' => false,
            'attribute_name' => 'Shop',
            'attribute_trusted' => true,
        ]);

        // untrusted app, and an older token without the claim
        $this->getJson('/client', ['Authorization' => 'Bearer '.$this->token(['client' => ['name' => 'Other', 'trusted' => false]])])
            ->assertOk()->assertJsonPath('trusted', false)->assertJsonPath('name', 'Other');
        $this->getJson('/client', ['Authorization' => 'Bearer '.$this->token()])->assertOk()->assertExactJson([
            'id' => '019a0000-0000-7000-8000-000000000001',
            'name' => null,
            'trusted' => false,
            'personal_access' => false,
            'attribute_name' => null,
            'attribute_trusted' => null,
        ]);

        // only a real `true` counts
        $this->getJson('/client', ['Authorization' => 'Bearer '.$this->token(['client' => ['trusted' => 'yes', 'personal_access' => 1]])])
            ->assertOk()->assertJsonPath('trusted', false)->assertJsonPath('personal_access', false);

        // a personal access token
        $this->getJson('/client', ['Authorization' => 'Bearer '.$this->token(['client' => ['name' => 'Personal Access Client', 'trusted' => true, 'personal_access' => true]])])
            ->assertOk()->assertJsonPath('personal_access', true);
    }

    public function test_acting_as_takes_further_claims(): void
    {
        $user = GuardUser::query()->find(1);

        AnikeenId::actingAs($user, ['console-containers'], 'api', ['aud' => 'client-of-shop', 'client' => ['name' => 'Shop', 'trusted' => true]]);

        $this->assertTrue($user->anikeenTokenCan('console-containers'));
        $this->assertSame('client-of-shop', $user->anikeenTokenClientId());
        $this->assertSame('Shop', $user->anikeenTokenClientName());
        $this->assertTrue($user->anikeenTokenClientTrusted());

        // the scopes argument wins over a `scopes` claim
        AnikeenId::actingAs($user, ['user'], 'api', ['scopes' => ['*']]);
        $this->assertSame(['user'], $user->anikeenToken()->scopes);
        $this->assertNull($user->anikeenTokenClientId());
        $this->assertFalse($user->anikeenTokenClientTrusted());
    }
}

class GuardUser extends Authenticatable
{
    use HasAnikeenTokens;

    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
