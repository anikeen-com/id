<?php

namespace Anikeen\Id;

use Anikeen\Id\ApiOperations\Request;
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
use Anikeen\Id\Helpers\Paginator;
use stdClass;
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
    use Request;

    /**
     * Get the currently authenticated user.
     *
     * @throws Throwable
     */
    public function getUserData(): stdClass
    {
        if (!isset($this->userDataCache)) {
            $this->userDataCache = $this->request('GET', 'v1/user')->data;
        }
        return $this->userDataCache;
    }

    /**
     * Make a request to the Anikeen API.
     *
     * @throws Throwable
     */
    public function request(string $method, string $path, null|array $payload = null, array $parameters = [], ?Paginator $paginator = null): Result
    {
        $anikeenId = new AnikeenId();
        $anikeenId->withToken($this->{AnikeenId::getAccessTokenField()});

        return $anikeenId->request($method, $path, $payload, $parameters, $paginator);
    }
}