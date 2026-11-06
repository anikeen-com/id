<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\CollectionException;
use Anikeen\Id\Exceptions\PreconditionFailedException;
use Anikeen\Id\Resources\SshKeys;
use Anikeen\Id\Support\AppRequest;

class RequestHeadersTest extends TestCase
{
    public function test_requests_without_extra_headers_send_the_same_headers_as_before(): void
    {
        $this->respond(200, ['data' => []]);

        AppRequest::send('GET', 'v1/client/subscriptions', null, ['page' => 1]);

        $request = $this->sent[0]['request'];
        $this->assertSame(['Bearer app-token'], $request->getHeader('Authorization'));
        $this->assertSame(['app-client'], $request->getHeader('Client-ID'));
        $this->assertFalse($request->hasHeader('If-Match'));
        $this->assertFalse($request->hasHeader('If-None-Match'));
        $this->assertSame('page=1', $request->getUri()->getQuery());
    }

    public function test_app_requests_send_extra_headers_also_on_the_retry_with_a_fresh_token(): void
    {
        $this->respond(401, ['message' => 'Unauthenticated.']);
        $this->respond(200, ['data' => []]);

        AppRequest::send('PUT', 'v1/x', ['value' => 1], [], ['If-Match' => '"7"']);

        $this->assertSame('"7"', $this->sent[0]['request']->getHeaderLine('If-Match'));
        $this->assertSame('"7"', $this->sent[1]['request']->getHeaderLine('If-Match'));
        $this->assertSame('Bearer fresh-app-token', $this->sent[1]['request']->getHeaderLine('Authorization'));
    }

    public function test_extra_headers_do_not_replace_the_client_and_token_headers(): void
    {
        $this->respond(200, ['data' => []]);

        app(AnikeenId::class)->withToken('user-token')
            ->put('v1/x', ['value' => 1], [], null, ['If-None-Match' => '*', 'Authorization' => 'Bearer other']);

        $request = $this->sent[0]['request'];
        $this->assertSame('*', $request->getHeaderLine('If-None-Match'));
        $this->assertSame('Bearer user-token', $request->getHeaderLine('Authorization'));
    }

    public function test_get_and_delete_helpers_take_headers(): void
    {
        $this->respond(200, ['data' => []]);
        $this->respond(204);

        $anikeenId = app(AnikeenId::class)->withToken('user-token');
        $anikeenId->get('v1/x', [], null, ['If-None-Match' => '"3"']);
        $anikeenId->delete('v1/x', [], [], null, ['If-Match' => '"3"']);

        $this->assertSame('"3"', $this->sent[0]['request']->getHeaderLine('If-None-Match'));
        $this->assertSame('"3"', $this->sent[1]['request']->getHeaderLine('If-Match'));
    }

    public function test_a_412_of_an_app_request_is_a_precondition_failed_exception(): void
    {
        $this->respond(412, ['message' => 'The preference has another version.', 'error' => 'preference_version_mismatch', 'current_version' => 12]);

        try {
            AppRequest::send('PUT', 'v1/x', ['value' => 1], [], ['If-Match' => '"7"']);
            $this->fail('no exception');
        } catch (PreconditionFailedException $e) {
            $this->assertSame(412, $e->status);
            $this->assertSame('preference_version_mismatch', $e->error);
            $this->assertSame(12, $e->currentVersion());
        }
    }

    public function test_a_412_of_a_user_request_is_a_precondition_failed_exception(): void
    {
        $this->respond(412, ['message' => 'The preference is already set.', 'error' => 'preference_exists', 'current_version' => null]);

        $result = app(AnikeenId::class)->withToken('user-token')->put('v1/x', ['value' => 1]);
        $e = ApiException::fromResult($result);

        $this->assertInstanceOf(PreconditionFailedException::class, $e);
        $this->assertSame('preference_exists', $e->error);
        $this->assertNull($e->currentVersion());
    }

    public function test_list_errors_keep_their_collection_exception_type(): void
    {
        $this->respond(412, ['message' => 'x', 'error' => 'x']);

        $this->expectException(CollectionException::class);

        $anikeenId = app(AnikeenId::class)->withToken('user-token');
        SshKeys::builder(fn () => $anikeenId->get('v1/ssh-keys'));
    }

    public function test_other_errors_stay_plain_api_exceptions(): void
    {
        $this->respond(409, ['message' => 'Taken.', 'error' => 'preference_value_taken']);

        try {
            AppRequest::send('PUT', 'v1/x', ['value' => 1]);
            $this->fail('no exception');
        } catch (ApiException $e) {
            $this->assertSame(ApiException::class, $e::class);
            $this->assertTrue($e->isConflict());
        }
    }
}
