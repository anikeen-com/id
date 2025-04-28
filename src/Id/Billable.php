<?php

namespace Anikeen\Id;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Concerns\ManagesAddresses;
use Anikeen\Id\Concerns\ManagesBalance;
use Anikeen\Id\Concerns\ManagesInvoices;
use Anikeen\Id\Concerns\ManagesOrders;
use Anikeen\Id\Concerns\ManagesPaymentMethods;
use Anikeen\Id\Concerns\ManagesSubscriptions;
use Anikeen\Id\Concerns\ManagesTaxation;
use Anikeen\Id\Concerns\ManagesTransactions;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Helpers\Paginator;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

trait Billable
{
    use ManagesAddresses;
    use ManagesBalance;
    use ManagesInvoices;
    use ManagesOrders;
    use ManagesPaymentMethods;
    use ManagesSubscriptions;
    use ManagesTaxation;
    use ManagesTransactions;
    use Request;

    protected stdClass|null $userData = null;

    /**
     * Get the currently authenticated user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    protected function getUserData(): stdClass
    {
        if (!$this->userData) {
            $this->userData = $this->request('GET', 'v1/user')->data;
        }
        return $this->userData;
    }

    /**
     * Make a request to the Anikeen API.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    protected function request(string $method, string $path, null|array $payload = null, array $parameters = [], Paginator $paginator = null): Result
    {
        $anikeenId = new AnikeenId();
        $anikeenId->withToken($this->{AnikeenId::getAccessTokenField()});

        return $anikeenId->request($method, $path, $payload, $parameters, $paginator);
    }
}