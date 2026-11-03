<?php

namespace Anikeen\Id\Tests;

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
}

class GuardUser extends Authenticatable
{
    use HasAnikeenTokens;

    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
