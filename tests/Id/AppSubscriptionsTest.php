<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AppSubscriptionsTest extends TestCase
{
    private function owner(?string $anikeenId = '01JUSER'): Model
    {
        $owner = new class extends Model {
            use Billable;

            protected $guarded = [];
        };
        $owner->anikeen_id = $anikeenId;
        $owner->anikeen_id_access_token = 'user-token';

        return $owner;
    }

    private function subscription(array $overrides = []): array
    {
        return ['data' => ['id' => '01JSUB', 'user_id' => '01JUSER', 'name' => 'Server', 'active' => false, 'cancelled' => false, ...$overrides]];
    }

    public function test_create_sends_the_user_id_with_the_app_token(): void
    {
        $this->respond(201, $this->subscription());

        $subscription = $this->owner()->appSubscriptions()->create(['group' => 'team-1', 'name' => 'Server', 'unit' => 'hour', 'price' => '0.0137', 'idempotency_key' => 'server:1']);

        $request = $this->sent[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/client/subscriptions', $request->getUri()->getPath());
        $this->assertSame('Bearer app-token', $request->getHeaderLine('Authorization'));
        $this->assertSame(['group' => 'team-1', 'name' => 'Server', 'unit' => 'hour', 'price' => '0.0137', 'idempotency_key' => 'server:1', 'user_id' => '01JUSER'], $this->sentJson(0));
        $this->assertSame('01JSUB', $subscription->id);
    }

    public function test_nothing_is_requested_until_a_method_is_called(): void
    {
        $this->owner()->appSubscriptions();

        $this->assertCount(0, $this->sent);
    }

    public function test_actions_use_the_app_endpoints(): void
    {
        $this->respond(201, $this->subscription());
        $this->respond(200, $this->subscription(['active' => true]));
        $this->respond(200, $this->subscription(['active' => true, 'autorenew' => false]));
        $this->respond(200, $this->subscription(['active' => true, 'autorenew' => true]));
        $this->respond(200, $this->subscription(['cancelled' => true]));

        $subscription = $this->owner()->appSubscriptions()->create(['name' => 'Server', 'unit' => 'hour', 'price' => 1]);

        $this->assertTrue($subscription->checkout()->active);
        $this->assertFalse($subscription->pause()->autorenew);
        $this->assertTrue($subscription->resume()->autorenew);
        $this->assertTrue($subscription->revoke(refund: true)->cancelled);

        $this->assertSame([
            'POST /api/v1/client/subscriptions',
            'PUT /api/v1/client/subscriptions/01JSUB/checkout',
            'PUT /api/v1/client/subscriptions/01JSUB/pause',
            'PUT /api/v1/client/subscriptions/01JSUB/resume',
            'PUT /api/v1/client/subscriptions/01JSUB/revoke',
        ], array_map(fn ($entry) => $entry['request']->getMethod() . ' ' . $entry['request']->getUri()->getPath(), $this->sent));
        $this->assertSame(['refund' => true], $this->sentJson(4));
    }

    public function test_errors_carry_status_and_error_code(): void
    {
        $this->respond(409, ['message' => 'Product subscription has already been cancelled.', 'error' => 'already_cancelled']);

        try {
            $this->owner()->appSubscriptions()->create(['name' => 'Server', 'unit' => 'hour', 'price' => 1]);
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('already_cancelled', $e->error);
            $this->assertTrue($e->isConflict());
            $this->assertFalse($e->isTemporary());
        }
    }

    public function test_find_returns_null_for_unknown_subscriptions_and_other_users(): void
    {
        $this->respond(404, ['message' => 'Subscription not found.', 'error' => 'subscription_not_found']);
        $this->respond(200, $this->subscription(['user_id' => '01JSOMEONEELSE']));
        $this->respond(200, $this->subscription());

        $subscriptions = $this->owner()->appSubscriptions();

        $this->assertNull($subscriptions->find('01JUNKNOWN'));
        $this->assertNull($subscriptions->find('01JSUB'));
        $this->assertSame('01JSUB', $subscriptions->find('01JSUB')->id);
    }

    public function test_all_follows_pages_and_filters_by_user(): void
    {
        $this->respond(200, ['data' => [['id' => 'a', 'user_id' => '01JUSER']], 'meta' => ['current_page' => 1, 'last_page' => 2, 'total' => 2], 'links' => ['next' => null]]);
        $this->respond(200, ['data' => [['id' => 'b', 'user_id' => '01JUSER']], 'meta' => ['current_page' => 2, 'last_page' => 2, 'total' => 2], 'links' => ['next' => null]]);

        $all = $this->owner()->appSubscriptions()->all(['group' => 'team-1', 'active' => true]);

        $this->assertSame(['a', 'b'], array_map(fn ($s) => $s->id, $all));
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame(['group' => 'team-1', 'active' => '1', 'user_id' => '01JUSER', 'per_page' => '100', 'page' => '1'], $query);
    }

    public function test_a_rejected_app_token_is_renewed_once(): void
    {
        $this->respond(401, ['message' => 'Unauthenticated.']);
        $this->respond(200, ['data' => [], 'meta' => ['last_page' => 1], 'links' => ['next' => null]]);

        $this->assertSame([], $this->owner()->appSubscriptions()->all());

        $this->assertSame('Bearer app-token', $this->sent[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer fresh-app-token', $this->sent[1]['request']->getHeaderLine('Authorization'));
    }

    public function test_an_unreachable_service_is_a_temporary_api_exception(): void
    {
        $this->http->append(new ConnectException('Connection refused', new Request('GET', 'v1/client/subscriptions')));

        try {
            $this->owner()->appSubscriptions()->all();
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(0, $e->status);
            $this->assertTrue($e->isTemporary());
        }
    }

    public function test_the_shared_client_keeps_the_users_token(): void
    {
        $this->respond(200, ['data' => [], 'meta' => ['last_page' => 1], 'links' => ['next' => null]]);
        app(AnikeenId::class)->withToken('user-token');

        $this->owner()->appSubscriptions()->all();

        $this->assertSame('user-token', app(AnikeenId::class)->getToken());
    }

    public function test_the_user_id_field_is_configurable(): void
    {
        AnikeenId::useUserIdField('id_service_user');
        $owner = $this->owner(null);
        $owner->id_service_user = '01JOTHER';

        try {
            $this->assertSame('01JOTHER', $owner->appSubscriptions()->userId());
        } finally {
            AnikeenId::useUserIdField('anikeen_id');
        }
    }

    public function test_a_missing_user_id_is_reported(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->owner(null)->appSubscriptions()->create(['name' => 'Server', 'unit' => 'hour', 'price' => 1]);
    }
}
