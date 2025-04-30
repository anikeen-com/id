<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

class Addresses extends BaseCollection
{
    use HasBillable;

    /**
     * Creates a new address for the current user.
     *
     * @param array{
     *       company_name: null|string,
     *       first_name:  string,
     *       last_name: string,
     *       address: string,
     *       address_2: null|string,
     *       house_number: null|string,
     *       postal_code: string,
     *       city: string,
     *       state: null|string,
     *       country_iso: string,
     *       phone_number: null|string,
     *       email: null|string,
     *       primary: bool,
     *       primary_billing_address: bool
     *   } $attributes The address data:
     * 　  - company_name:            Company name (optional)
     * 　  - first_name:              First name
     * 　  - last_name:               Last name
     * 　  - address:                 Address line 1 (e.g. street address, P.O. Box, etc.)
     * 　  - address_2:               Address line 2 (optional, e.g. apartment number, c/o, etc.)
     * 　  - house_number:            House number (optional)
     * 　  - postal_code:             Postal code
     * 　  - city:                    City
     * 　  - state:                   State (optional, e.g. province, region, etc.)
     * 　  - country_iso:             Country ISO code (e.g. US, CA, etc.)
     * 　  - phone_number:            Phone number (optional)
     * 　  - email:                   Email address (optional, e.g. for delivery notifications)
     * 　  - primary:                 Whether this address should be the primary address (optional)
     * 　  - primary_billing_address: Whether this address should be the primary billing address (optional)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function create(array $attributes = []): Address
    {
        return (new Address($this->billable->request('POST', 'v1/addresses', $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $id): ?Address
    {
        $result = $this->billable->request('GET', sprintf('v1/addresses/%s', $id));

        return $result->success() ?
            (new Address($result))
                ->setBillable($this->billable)
            : null;
    }

    /**
     * Get default address from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function defaultBillingAddress(): Address
    {
        return (new Address($this->billable->request('GET', sprintf('v1/addresses/%s', $this->billable->getUserData()->billing_address_id))))
            ->setBillable($this->billable);
    }

    /**
     * Check if the current user has a default billing address.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function hasDefaultBillingAddress(): bool
    {
        return $this->billable->getUserData()->billing_address_id !== null;
    }
}