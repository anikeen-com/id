<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Enums\Appearance;
use Anikeen\Id\Enums\SharedPreference;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\PreconditionFailedException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PreferencesTest extends TestCase
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

    private function preference(string $key, mixed $value, int $version = 7): array
    {
        return ['data' => ['namespace' => 'global', 'key' => $key, 'value' => $value, 'version' => $version, 'updated_at' => '2026-11-06T10:00:00.000000Z', 'updated_by' => ['type' => 'user', 'client_id' => 'app-client']]];
    }

    private function path(int $index): string
    {
        return $this->sent[$index]['request']->getUri()->getPath();
    }

    public function test_the_lookup_returns_null_when_nobody_holds_the_value(): void
    {
        $this->respond(404, ['message' => 'The preference is not set.', 'error' => 'preference_not_found']);

        $this->assertNull(app(AnikeenId::class)->findUserIdByPreference('acme', 'customer_id', 'cus_123'));
    }

    public function test_the_lookup_throws_on_other_not_found_answers(): void
    {
        $this->respond(404, ['message' => 'The namespace does not exist.', 'error' => 'namespace_not_found']);

        try {
            app(AnikeenId::class)->findUserIdByPreference('acme', 'customer_id', 'cus_123');
            $this->fail('no exception');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->status);
            $this->assertSame('namespace_not_found', $e->error);
        }
    }

    public function test_the_lookup_throws_on_a_404_without_an_error_code(): void
    {
        $this->respond(404, ['message' => 'Not Found']);

        $this->expectException(ApiException::class);

        app(AnikeenId::class)->findUserIdByPreference('acme', 'customer_id', 'cus_123');
    }

    public function test_the_lookup_throws_on_validation_errors(): void
    {
        $this->respond(422, ['message' => 'The key is not unique.', 'error' => 'key_not_unique']);

        try {
            app(AnikeenId::class)->findUserIdByPreference('acme', 'plan', 'pro');
            $this->fail('no exception');
        } catch (ApiException $e) {
            $this->assertSame('key_not_unique', $e->error);
        }
    }

    public function test_a_failed_app_write_carries_the_current_version(): void
    {
        $this->respond(412, ['message' => 'The preference has another version.', 'error' => 'preference_version_mismatch', 'current_version' => 12]);

        try {
            app(AnikeenId::class)->setPreferenceByUserId('01JUSER', 'acme', 'plan', 'pro', ifVersion: 7);
            $this->fail('no exception');
        } catch (PreconditionFailedException $e) {
            $this->assertSame(412, $e->status);
            $this->assertSame('preference_version_mismatch', $e->error);
            $this->assertSame(12, $e->currentVersion());
        }
    }

    public function test_a_create_only_write_of_a_set_key_fails(): void
    {
        $this->respond(412, ['message' => 'The preference is already set.', 'error' => 'preference_exists', 'current_version' => 3]);

        try {
            app(AnikeenId::class)->setPreferenceByUserId('01JUSER', 'acme', 'plan', 'pro', createOnly: true);
            $this->fail('no exception');
        } catch (PreconditionFailedException $e) {
            $this->assertSame('preference_exists', $e->error);
            $this->assertSame(3, $e->currentVersion());
        }
    }

    public function test_a_failed_user_write_carries_the_current_version(): void
    {
        $this->respond(412, ['message' => 'The preference has another version.', 'error' => 'preference_version_mismatch', 'current_version' => null]);
        $this->respond(412, ['message' => 'The preference has another version.', 'error' => 'preference_version_mismatch', 'current_version' => 5]);

        foreach ([
            fn () => $this->owner()->setGlobalPreference('appearance', 'dark', ifVersion: 4),
            fn () => $this->owner()->deleteGlobalPreference('appearance', ifVersion: 4),
        ] as $i => $call) {
            try {
                $call();
                $this->fail('no exception');
            } catch (PreconditionFailedException $e) {
                $this->assertSame([null, 5][$i], $e->currentVersion());
            }
        }
    }

    public function test_a_write_is_either_conditional_or_create_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            app(AnikeenId::class)->setPreferenceByUserId('01JUSER', 'acme', 'plan', 'pro', ifVersion: 7, createOnly: true);
        } finally {
            $this->assertCount(0, $this->sent);
        }
    }

    public function test_writes_clear_the_cached_preferences(): void
    {
        $owner = $this->owner();

        $this->respond(200, ['data' => [$this->preference('appearance', 'light')['data']]]);
        $this->respond(200, $this->preference('appearance', 'dark', 8));
        $this->respond(200, ['data' => [$this->preference('appearance', 'dark', 8)['data']]]);
        $this->respond(204);
        $this->respond(200, ['data' => []]);

        $this->assertSame('light', $owner->globalPreference(SharedPreference::APPEARANCE));
        $this->assertSame('light', $owner->globalPreference(SharedPreference::APPEARANCE)); // cached
        $owner->setGlobalPreference(SharedPreference::APPEARANCE, Appearance::DARK);
        $this->assertSame('dark', $owner->globalPreference(SharedPreference::APPEARANCE));
        $owner->deleteGlobalPreference(SharedPreference::APPEARANCE);
        $this->assertSame(Appearance::SYSTEM, $owner->globalPreference(SharedPreference::APPEARANCE, Appearance::SYSTEM));

        $this->assertCount(5, $this->sent);
        $this->assertSame(['GET', 'PUT', 'GET', 'DELETE', 'GET'], array_map(fn ($sent) => $sent['request']->getMethod(), $this->sent));
    }

    public function test_a_failed_write_clears_the_cached_preferences_too(): void
    {
        $owner = $this->owner();

        $this->respond(200, ['data' => [$this->preference('appearance', 'light')['data']]]);
        $this->respond(412, ['message' => 'x', 'error' => 'preference_version_mismatch', 'current_version' => 9]);
        $this->respond(200, ['data' => [$this->preference('appearance', 'dark', 9)['data']]]);

        $owner->globalPreferences();

        try {
            $owner->setGlobalPreference('appearance', 'dark', ifVersion: 7);
        } catch (PreconditionFailedException) {
        }

        $this->assertSame('dark', $owner->globalPreference('appearance'));
        $this->assertCount(3, $this->sent);
    }

    public function test_path_segments_are_encoded(): void
    {
        $this->respond(200, ['data' => []]);
        $this->respond(204);
        $this->respond(200, ['data' => ['user_id' => '01JUSER']]);

        $anikeenId = app(AnikeenId::class);
        $anikeenId->preferencesByUserId('a/b', 'x y');
        $anikeenId->deletePreferenceByUserId('01JUSER', 'acme', '../key?');
        $anikeenId->findUserIdByPreference('ac/me', 'k', 'v');

        $this->assertSame('/api/v1/client/users/a%2Fb/preferences/x%20y', $this->path(0));
        $this->assertSame('/api/v1/client/users/01JUSER/preferences/acme/..%2Fkey%3F', $this->path(1));
        $this->assertSame('/api/v1/client/preferences/ac%2Fme/lookup', $this->path(2));
    }

    public function test_values_round_trip_as_json(): void
    {
        $this->respond(201, $this->preference('settings', (object) []));

        $preference = app(AnikeenId::class)->setPreferenceByUserId('01JUSER', 'acme', 'settings', (object) []);

        $this->assertSame('{"value":{}}', (string) $this->sent[0]['request']->getBody());
        $this->assertEquals((object) [], $preference->value);
    }

    public function test_a_deleted_user_is_not_found(): void
    {
        $this->respond(404, ['message' => 'The user does not exist.', 'error' => 'user_not_found']);

        try {
            app(AnikeenId::class)->preferenceByUserId('01JGONE', 'acme', 'plan');
            $this->fail('no exception');
        } catch (ApiException $e) {
            $this->assertTrue($e->isNotFound());
            $this->assertSame('user_not_found', $e->error);
        }
    }

    public function test_an_unreachable_service_is_temporary(): void
    {
        $this->http->append(new \GuzzleHttp\Exception\ConnectException('down', new \GuzzleHttp\Psr7\Request('DELETE', 'x')));

        try {
            $this->owner()->deleteGlobalPreference('appearance');
            $this->fail('no exception');
        } catch (ApiException $e) {
            $this->assertTrue($e->isTemporary());
        }
    }
}
