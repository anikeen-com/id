<?php

namespace Anikeen\Id\Tests;

use Anikeen\Id\Billable;
use Illuminate\Database\Eloquent\Model;

class BillableModelTest extends TestCase
{
    public function test_cached_api_results_are_not_model_attributes(): void
    {
        $user = new class extends Model {
            use Billable;

            protected $guarded = [];

            public function cacheAll(): void
            {
                foreach (['addresses', 'countries', 'defaultPaymentMethod', 'invoices', 'orders', 'paymentMethods', 'subscriptions', 'transactions', 'userData'] as $name) {
                    $this->{$name.'Cache'} = 'cached';
                }

                $this->preferencesCache['global'] = 'cached';
            }
        };
        $user->email = 'ada@example.com';

        $user->cacheAll();

        // save() would otherwise write them as columns ("no such column: ordersCache")
        $this->assertSame(['email' => 'ada@example.com'], $user->getAttributes());
    }
}
