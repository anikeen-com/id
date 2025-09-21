<?php

namespace Anikeen\Id;

use Anikeen\Id\Concerns\ManagesAddresses;
use Anikeen\Id\Concerns\ManagesBalance;
use Anikeen\Id\Concerns\ManagesCountries;
use Anikeen\Id\Concerns\ManagesInvoices;
use Anikeen\Id\Concerns\ManagesOrders;
use Anikeen\Id\Concerns\ManagesPaymentMethods;
use Anikeen\Id\Concerns\ManagesProfile;
use Anikeen\Id\Concerns\ManagesSubscriptions;
use Anikeen\Id\Concerns\ManagesTaxation;
use Anikeen\Id\Concerns\ManagesTransactions;
use Throwable;

trait Billable
{
    use ManagesAddresses;
    use ManagesBalance;
    use ManagesCountries;
    use ManagesInvoices;
    use ManagesOrders;
    use ManagesPaymentMethods;
    use ManagesProfile;
    use ManagesSubscriptions;
    use ManagesTaxation;
    use ManagesTransactions;

    /**
     * Get the currently authenticated user.
     *
     * @throws Throwable
     */
    public function getUserData(): object
    {
        if (!isset($this->userDataCache)) {
            $this->userDataCache = $this->anikeenId()->request('GET', 'v1/user')->data;
        }
        return $this->userDataCache;
    }

    /**
     * Get the AnikeenId class.
     *
     * @throws Throwable
     */
    public function anikeenId(): AnikeenId
    {
        return app(AnikeenId::class)->withToken($this->{AnikeenId::getAccessTokenField()});
    }
}
