<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\ApiOperations\Request;
use Anikeen\Id\Exceptions\RequestRequiresClientIdException;
use Anikeen\Id\Result;
use GuzzleHttp\Exception\GuzzleException;

trait ManagesAddresses
{
    use Request;

    /**
     * Get addresses from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function addresses(): Result
    {
        return $this->request('GET', 'v1/addresses');
    }

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
    public function createAddress(array $attributes = []): Result
    {
        return $this->request('POST', 'v1/addresses', $attributes);
    }

    /**
     * Get given address from the current user.
     *
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function address(string $addressId): Result
    {
        return $this->request('GET', sprintf('v1/addresses/%s', $addressId));
    }

    /**
     * Update given address from the current user.
     *
     * VAT is calculated based on the billing address and shown in the address response.
     *
     * @param string $addressId The address ID.
     * @param array{
     *      company_name: null|string,
     *      first_name:  string,
     *      last_name: string,
     *      address_2: null|string,
     *      address: string,
     *      house_number: null|string,
     *      postal_code: string,
     *      city: string,
     *      state: null|string,
     *      country_iso: string,
     *      phone_number: null|string,
     *      email: null|string,
     *      primary: bool,
     *      primary_billing_address: bool
     *  } $attributes The address data:
     *    - company_name:            Company name (optional)
     *    - first_name:              First name (required when set)
     *    - last_name:               Last name (required when set)
     *    - address:                 Address line 1 (e.g. street address, P.O. Box, etc.)
     *    - address_2:               Address line 2 (optional, e.g. apartment number, c/o, etc.)
     *    - house_number:            House number (optional)
     *    - postal_code:             Postal code (required when set)
     *    - city:                    City (required when set)
     *    - state:                   State (optional, e.g. province, region, etc.)
     *    - country_iso:             Country ISO code (required when set, e.g. US, CA, etc.)
     *    - phone_number:            Phone number (optional)
     *    - email:                   Email address (optional, e.g. for delivery notifications)
     *    - primary:                 Whether this address should be the primary address (optional)
     *    - primary_billing_address: Whether this address should be the primary billing address (optional)
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function updateAddress(string $addressId, array $attributes = []): Result
    {
        return $this->request('PUT', sprintf('v1/addresses/%s', $addressId), $attributes);
    }

    /**
     * Delete given address from the current user.
     *
     * @param string $addressId The address ID.
     * @throws RequestRequiresClientIdException
     * @throws GuzzleException
     */
    public function deleteAddress(string $addressId): Result
    {
        return $this->request('DELETE', sprintf('v1/addresses/%s', $addressId));
    }
}