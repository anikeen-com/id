<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\PreconditionFailedException;
use Anikeen\Id\Exceptions\ValidationFailedException;
use Illuminate\Database\Eloquent\Model;

class ValidationFailedTest extends TestCase
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

    public function test_422_of_app_requests_is_a_validation_exception(): void
    {
        $this->respond(422, ['message' => 'The value is too large.', 'error' => 'value_too_large']);

        try {
            app(AnikeenId::class)->setPreferenceByUserId('01JUSER', 'example', 'theme', str_repeat('x', 10));
            $this->fail('No exception thrown');
        } catch (ValidationFailedException $e) {
            $this->assertSame('value_too_large', $e->error);
            $this->assertSame([], $e->errorCodes());
            $this->assertTrue($e->hasErrorCode('value_too_large'));
        }
    }

    public function test_412_stays_a_precondition_failure(): void
    {
        $this->respond(412, ['message' => 'Version mismatch.', 'error' => 'preference_version_mismatch', 'current_version' => 3]);

        $this->expectException(PreconditionFailedException::class);
        $this->owner()->setGlobalPreference('appearance', 'dark', ifVersion: 2);
    }

    public function test_422_of_user_requests_is_a_validation_exception(): void
    {
        $this->respond(422, ['message' => 'Unknown value.', 'error' => 'invalid_shared_value']);

        try {
            $this->owner()->setGlobalPreference('appearance', 'purple');
            $this->fail('No exception thrown');
        } catch (ValidationFailedException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('invalid_shared_value', $e->error);
            $this->assertSame([], $e->errorCodes());
        }
    }
}
