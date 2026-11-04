<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Billable;
use Anikeen\Id\Enums\SubscriptionStatus;
use Anikeen\Id\Resources\AppSubscription;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;

class SubscriptionStatusTest extends TestCase
{
    private const BASE = ['id' => '01JSUB', 'active' => false, 'autorenew' => true, 'cancelled' => false, 'starts_at' => null, 'cancels_at' => null, 'trial_ends_at' => null];

    public static function states(): array
    {
        $started = ['starts_at' => '2026-10-01T00:00:00+00:00'];

        return [
            'draft' => [[], SubscriptionStatus::DRAFT, false],
            'active' => [['active' => true, ...$started], SubscriptionStatus::ACTIVE, true],
            'trial' => [['active' => true, 'trial_ends_at' => date(DATE_ATOM, time() + 86400), ...$started], SubscriptionStatus::TRIAL, true],
            'trial over' => [['active' => true, 'trial_ends_at' => '2026-01-01T00:00:00+00:00', ...$started], SubscriptionStatus::ACTIVE, true],
            'paused' => [['active' => true, 'autorenew' => false, ...$started], SubscriptionStatus::PAUSED, true],
            'cancelling' => [['active' => true, 'cancels_at' => '2027-01-01T00:00:00+00:00', ...$started], SubscriptionStatus::CANCELLING, true],
            'expired' => [['autorenew' => false, ...$started], SubscriptionStatus::EXPIRED, false],
            'cancelled' => [['autorenew' => false, 'cancelled' => true, ...$started], SubscriptionStatus::CANCELLED, false],
        ];
    }

    #[DataProvider('states')]
    public function test_the_status_follows_the_fields(array $fields, string $status, bool $running): void
    {
        $data = [...self::BASE, ...$fields];

        $this->respond(200, ['data' => [$data]]); // subscriptions() loads the list
        $this->respond(200, ['data' => $data]);
        $owner = new class extends Model {
            use Billable;
        };
        $owner->anikeen_id_access_token = 'user-token';
        $subscription = $owner->subscriptions()->find('01JSUB');

        $app = new AppSubscription((object) $data);

        foreach ([$subscription, $app] as $resource) {
            $this->assertSame($status, $resource->status());
            $this->assertSame($running, $resource->isActive());
            $this->assertSame($status === SubscriptionStatus::DRAFT, $resource->isDraft());
            $this->assertSame($status === SubscriptionStatus::PAUSED, $resource->isPaused());
            $this->assertSame($status === SubscriptionStatus::CANCELLING, $resource->isCancelling());
            $this->assertSame($status === SubscriptionStatus::EXPIRED, $resource->isExpired());
            $this->assertSame($status === SubscriptionStatus::CANCELLED, $resource->isCancelled());
        }
    }
}
