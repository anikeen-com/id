<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\AnikeenId;
use Anikeen\Id\Billable;
use Anikeen\Id\Exceptions\ApiException;
use Anikeen\Id\Resources\AppCheckoutSession;
use Anikeen\Id\Resources\AppSubscription;
use Anikeen\Id\Resources\CheckoutSession;
use Anikeen\Id\Webhooks\EventType;
use Anikeen\Id\Webhooks\Webhook;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CheckoutSessionsTest extends TestCase
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

    private function sessionData(array $overrides = []): array
    {
        return ['data' => [
            'object' => 'checkout_session',
            'id' => '01JCS',
            'user_id' => '01JUSER',
            'status' => 'open',
            'return_url' => 'https://console.test/builder/done',
            'metadata' => ['cart' => '42'],
            'confirmation_url' => 'https://id.anikeen.com/checkout/sessions/01JCS',
            'subscription_ids' => ['01JS1', '01JS2'],
            'subscriptions' => [
                ['id' => '01JS1', 'user_id' => '01JUSER', 'name' => 'Cloud Server S', 'active' => false, 'payload' => ['product' => 'server-s']],
                ['id' => '01JS2', 'user_id' => '01JUSER', 'name' => 'Backup', 'active' => false],
            ],
            ...$overrides,
        ]];
    }

    private function request(int $index): string
    {
        $request = $this->sent[$index]['request'];

        return $request->getMethod() . ' ' . $request->getUri()->getPath() . ' ' . $request->getHeaderLine('Authorization');
    }

    public function test_a_trusted_app_opens_a_session_with_its_own_token(): void
    {
        $this->respond(201, $this->sessionData());

        $session = $this->owner()->appCheckoutSessions()->create([
            'return_url' => 'https://console.test/builder/done',
            'idempotency_key' => 'cart:42',
            'subscriptions' => [
                ['group' => 'team-1', 'name' => 'Cloud Server S', 'unit' => 'hour', 'price' => '0.0137', 'payload' => ['product' => 'server-s']],
                ['id' => '01JS2'],
            ],
        ]);

        $this->assertSame('POST /api/v1/client/checkout-sessions Bearer app-token', $this->request(0));
        $this->assertSame('01JUSER', $this->sentJson(0)['user_id']);
        $this->assertSame([['group' => 'team-1', 'name' => 'Cloud Server S', 'unit' => 'hour', 'price' => '0.0137', 'payload' => ['product' => 'server-s']], ['id' => '01JS2']], $this->sentJson(0)['subscriptions']);

        $this->assertInstanceOf(AppCheckoutSession::class, $session);
        $this->assertSame('https://id.anikeen.com/checkout/sessions/01JCS', $session->confirmationUrl());
        $this->assertTrue($session->isOpen());
        $this->assertSame(['01JS1', '01JS2'], $session->subscriptionIds());
        $this->assertContainsOnlyInstancesOf(AppSubscription::class, $session->subscriptions());
        $this->assertSame('server-s', $session->subscriptions()[0]->payload->product);
        $this->assertSame('42', $session->metadata->cart);
    }

    public function test_the_app_finds_refreshes_and_cancels_sessions(): void
    {
        $this->respond(200, $this->sessionData(['status' => 'completed', 'confirmation_url' => null]));
        $this->respond(200, $this->sessionData(['user_id' => 'someone-else']));
        $this->respond(404, ['message' => 'Checkout session not found.', 'error' => 'checkout_session_not_found']);
        $this->respond(200, $this->sessionData());
        $this->respond(200, $this->sessionData(['status' => 'cancelled', 'confirmation_url' => null]));
        $this->respond(409, ['message' => 'The checkout session is cancelled.', 'error' => 'checkout_session_cancelled']);

        $sessions = $this->owner()->appCheckoutSessions();

        $completed = $sessions->find('01JCS');
        $this->assertTrue($completed->isCompleted());
        $this->assertNull($completed->confirmationUrl());
        $this->assertNull($sessions->find('01JCS'), 'another user\'s session');
        $this->assertNull($sessions->find('unknown'));

        $session = app(AnikeenId::class)->appCheckoutSessions()->find('01JCS'); // e.g. from a webhook
        $session->cancel();
        $this->assertTrue($session->isCancelled());
        $this->assertSame('POST /api/v1/client/checkout-sessions/01JCS/cancel Bearer app-token', $this->request(4));

        try {
            $session->cancel();
            $this->fail('Expected an ApiException.');
        } catch (ApiException $e) {
            $this->assertTrue($e->isConflict());
            $this->assertSame('checkout_session_cancelled', $e->error);
        }
    }

    public function test_without_a_billable_the_user_id_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(AnikeenId::class)->appCheckoutSessions()->create(['subscriptions' => [['id' => '01JS1']]]);
    }

    public function test_any_app_opens_a_session_with_the_users_token(): void
    {
        $this->respond(201, $this->sessionData());
        $this->respond(200, $this->sessionData(['status' => 'expired', 'confirmation_url' => null]));
        $this->respond(404, ['message' => 'Checkout session not found.', 'error' => 'checkout_session_not_found']);

        $user = $this->owner();
        $session = $user->checkoutSessions()->create(['subscriptions' => [['name' => 'Pro', 'unit' => 'month', 'price' => 4.99]]]);

        $this->assertSame('POST /api/v1/checkout-sessions Bearer user-token', $this->request(0));
        $this->assertArrayNotHasKey('user_id', $this->sentJson(0));
        $this->assertInstanceOf(CheckoutSession::class, $session);
        $this->assertSame('https://id.anikeen.com/checkout/sessions/01JCS', $session->confirmationUrl());
        $this->assertSame(['01JS1', '01JS2'], $session->subscriptionIds());

        $expired = $user->checkoutSessions()->find('01JCS');
        $this->assertTrue($expired->isCancelled());
        $this->assertSame(CheckoutSession::STATUS_EXPIRED, $expired->status);
        $this->assertNull($user->checkoutSessions()->find('unknown'));
    }

    public function test_session_subscriptions_carry_a_free_trial(): void
    {
        $this->respond(201, $this->sessionData());

        $this->owner()->appCheckoutSessions()->create(['subscriptions' => [
            ['group' => 'team-1', 'name' => 'Pro', 'unit' => 'month', 'price' => '9.99', 'trial_days' => 14, 'trial_once' => true],
            ['group' => 'team-1', 'name' => 'Backup', 'unit' => 'month', 'price' => '1.99', 'trial_ends_at' => '2026-12-01T00:00:00+00:00'],
        ]]);

        $this->assertSame(14, $this->sentJson(0)['subscriptions'][0]['trial_days']);
        $this->assertTrue($this->sentJson(0)['subscriptions'][0]['trial_once']);
        $this->assertSame('2026-12-01T00:00:00+00:00', $this->sentJson(0)['subscriptions'][1]['trial_ends_at']);
    }

    public function test_nothing_is_requested_until_a_method_is_called(): void
    {
        $this->owner()->checkoutSessions();
        $this->owner()->appCheckoutSessions();

        $this->assertCount(0, $this->sent);
    }

    public function test_checkout_webhooks_carry_the_session(): void
    {
        $payload = json_encode(['id' => '01JEV', 'type' => 'checkout.completed', 'created' => time(), 'data' => ['object' => $this->sessionData(['status' => 'completed'])['data']]]);
        $timestamp = time();
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

        $event = Webhook::constructEvent($payload, $signature, 'whsec_test');

        $this->assertSame(EventType::CHECKOUT_COMPLETED, $event['type']);
        $this->assertSame(['01JS1', '01JS2'], $event['data']['object']['subscription_ids']);
        $this->assertContains('checkout.expired', EventType::all());
        $this->assertContains('subscription.activated', EventType::all());
        $this->assertContains('subscription.trial_will_end', EventType::all());
    }
}
