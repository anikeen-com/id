<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Throwable;

/**
 * @property string $id
 * @property null|string $company_name
 * @property null|string $first_name
 * @property null|string $last_name
 * @property null|string $address_2
 * @property null|string $address
 * @property null|string $house_number
 * @property null|string $postal_code
 * @property null|string $city
 * @property null|string $state
 * @property string $country_iso
 * @property null|string $phone_number
 * @property null|string $email
 * @property bool $primary
 * @property bool $primary_billing_address
 */
class Address extends BaseResource
{
    use HasBillable;

    /**
     * Update given address from the current user.
     *
     * VAT is calculated based on the billing address and shown in the address response.
     *
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
     * @throws Throwable
     */
    public function update(array $attributes = []): self
    {
        return (new self(fn() => $this->billable->request('PUT', sprintf('v1/addresses/%s', $this->id), $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Delete given address from the current user.
     *
     * @throws Throwable
     */
    public function delete(): bool
    {
        return $this->billable->request('DELETE', sprintf('v1/addresses/%s', $this->id))->success();
    }
}