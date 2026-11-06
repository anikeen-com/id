<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;
use Anikeen\Id\Concerns\HasConfirmationUrl;
use Throwable;

/**
 * @property string $id
 * @property null|string $confirmation_url where the user confirms it (third-party apps, or `confirm`)
 * @property null|string $oauth_client_id the app that bills the order: the app that created it, or for a monthly
 *                                        (collective) order the app whose subscriptions it bills; collective orders
 *                                        are kept per app. null for Anikeen's own (and legacy orders of several apps)
 * @property null|string $group
 * @property null|object $metadata the app's own data, returned as is
 * @property null|string $return_url where the user returns after confirming
 * @property null|string $expires_at when a draft created with `confirm` is cancelled unless confirmed
 */
class Order extends BaseResource
{
    use HasBillable;
    use HasConfirmationUrl;

    /**
     * Update given order from the current user.
     *
     * VAT is calculated based on the billing address and shown in the order response.
     *
     * The billing and shipping addresses are each persisted as standalone Address entities
     * in the database, but are also embedded (deep-copied) into the Order object itself
     * rather than merely referenced. This guarantees that the order retains its own snapshot
     * of both addresses for future reference.
     *
     * @param array{
     *      billing_address: array{
     *           company_name: null|string,
     *           first_name:  null|string,
     *           last_name: null|string,
     *           address: null|string,
     *           address_2: null|string,
     *           house_number: null|string,
     *           city: null|string,
     *           state: null|string,
     *           postal_code: null|string,
     *           country_iso: string,
     *           phone_number: null|string,
     *           email: null|string
     *      },
     *      shipping_address: null|array{
     *           company_name: null|string,
     *           first_name: string,
     *           last_name: string,
     *           address: string,
     *           address_2: null|string,
     *           house_number: null|string,
     *           city: string,
     *           state: null|string,
     *           postal_code: string,
     *           country_iso: string,
     *           phone_number: null|string,
     *           email: null|string
     *      },
     *      group?: null|string,
     *      metadata?: null|array
     *  } $attributes The order data:
     *    - billing_address:  Billing address (ISO 3166-1 alpha-2 country code)
     *    - shipping_address: Shipping address for physical items (first name, last name, address, postal code, city, ISO 3166-1 alpha-2 country code)
     *    - group:            The app's group (optional)
     *    - metadata:         The app's own data (max. 50 keys), only while the order is a draft (optional)
     * @throws Throwable
     */
    public function update(array $attributes = []): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/orders/%s', $this->id), $attributes)))
            ->setBillable($this->billable);
    }

    /**
     * Checkout given order from the current user.
     *
     * A draft created with `confirm` can't be checked out once it expired (409 "confirmation_expired").
     *
     * @throws Throwable
     */
    public function checkout(): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/orders/%s/checkout', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Revoke given order from the current user.
     *
     * @throws Throwable
     */
    public function revoke(): self
    {
        return (new self(fn() => $this->billable->anikeenId()
            ->request('PUT', sprintf('v1/orders/%s/revoke', $this->id))))
            ->setBillable($this->billable);
    }

    /**
     * Delete given order from the current user.
     *
     * @throws Throwable
     */
    public function delete(): bool
    {
        return $this->billable->anikeenId()
            ->request('DELETE', sprintf('v1/orders/%s', $this->id))->success();
    }

    /**
     * Deliveries of this order's goods; `create()` ships them (see Shipments::create()).
     *
     * @throws Throwable
     */
    public function shipments(): Shipments
    {
        return Shipments::builder(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/orders/%s/shipments', $this->id)))
            ->setBillable($this->billable)
            ->setParent($this);
    }

    /**
     * Get order items from given order.
     *
     * @throws Throwable
     */
    public function orderItems(array $parameters = []): OrderItems
    {
        return OrderItems::builder(fn() => $this->billable->anikeenId()
            ->request('GET', sprintf('v1/orders/%s/items', $this->id), [], $parameters))
            ->setBillable($this->billable)
            ->setParent($this);
    }
}