<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Exceptions\CollectionException;
use Anikeen\Id\Exceptions\ConfirmationRequiredException;
use Anikeen\Id\Exceptions\ResourceException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;

class UserApiExceptionsTest extends TestCase
{
    private function user(): Model
    {
        $user = new class extends Model {
            use Billable;
        };
        $user->anikeen_id_access_token = 'user-token';

        return $user;
    }

    public function test_a_refused_request_carries_status_and_error_code(): void
    {
        $this->respond(200, ['data' => []]); // orders() loads the list
        $this->respond(422, ['message' => 'The billing address is missing.', 'error' => 'billing_address_missing']);

        try {
            $this->user()->orders()->create(['items' => []]);
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertInstanceOf(ResourceException::class, $e, 'existing catch (ResourceException) blocks keep working');
            $this->assertSame(422, $e->status);
            $this->assertSame(422, $e->getCode());
            $this->assertSame('billing_address_missing', $e->error);
            $this->assertSame('The billing address is missing', $e->getMessage());
            $this->assertFalse($e->isTemporary());
        }
    }

    public function test_an_unknown_id_is_not_found(): void
    {
        $this->respond(200, ['data' => []]);
        $this->respond(404, ['message' => 'Not found.']);

        try {
            $this->user()->orders()->find('01JMISSING');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isNotFound());
            $this->assertNull($e->error);
        }
    }

    public function test_a_failed_list_is_an_api_exception_too(): void
    {
        $this->respond(503, ['message' => 'Maintenance.']);

        try {
            $this->user()->invoices();
            $this->fail('Expected a CollectionException.');
        } catch (CollectionException $e) {
            $this->assertInstanceOf(ApiException::class, $e);
            $this->assertSame(503, $e->status);
            $this->assertTrue($e->isTemporary());
        }
    }

    public function test_no_response_is_temporary_with_status_zero(): void
    {
        $this->respond(200, ['data' => []]);
        $this->http->append(new ConnectException('Connection refused', new Request('GET', 'v1/orders/01J')));

        try {
            $this->user()->orders()->find('01J');
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(0, $e->status);
            $this->assertTrue($e->isTemporary());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    public function test_confirmation_required_stays_its_own_exception(): void
    {
        $this->respond(200, ['data' => []]);
        $this->respond(200, ['data' => ['id' => '01JORDER', 'status' => 'draft']]);
        $this->respond(403, ['message' => 'Confirm it on Anikeen ID.', 'error' => 'confirmation_required', 'confirmation_url' => 'https://id.test/confirm/01JORDER']);

        $this->expectException(ConfirmationRequiredException::class);

        $this->user()->orders()->find('01JORDER')->checkout();
    }
}
