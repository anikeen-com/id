<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\AppShipping;

trait ManagesShipping
{
    /**
     * The app's shipping zones and what shipping costs for an address, with the app's own token
     * (scope inventory:client, any app). Orders created with `calculate_shipping` are priced by
     * these zones: Anikeen ID adds the shipping from the customer's shipping address.
     */
    public function shipping(): AppShipping
    {
        return new AppShipping();
    }
}
