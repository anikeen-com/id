<?php

namespace Anikeen\Id\Exceptions;

use Anikeen\Id\Enums\PricingError;
use Anikeen\Id\Result;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * A request to the ID service failed. `status` is the HTTP status (0 when the service could not
 * be reached), `error` the service's stable error code when it sent one (e.g. "already_cancelled").
 */
class ApiException extends ResourceException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $error = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * The exception for a failed request: Anikeen ID's message and error code, the HTTP status
     * (0 when there was no response).
     */
    public static function fromResult(Result $result): static
    {
        $body = is_object($result->data) ? $result->data : null;
        $status = $result->response()?->getStatusCode() ?? 0;

        // a failed conditional write; subclasses (CollectionException) keep their own type
        if ($status === 412 && static::class === self::class) {
            return PreconditionFailedException::fromResult($result);
        }

        // refused input, with per-field codes on some endpoints
        if ($status === 422 && static::class === self::class) {
            return ValidationFailedException::fromResult($result);
        }

        return new static(
            rtrim((string) ($body->message ?? $result->error()), '.'),
            $status,
            $body->error ?? null,
            $result->exception,
        );
    }

    /**
     * The exception for a request that got no response (network error, timeout).
     */
    public static function unreachable(Throwable $previous): static
    {
        return new static(sprintf('Anikeen ID is not reachable: %s', $previous->getMessage()), 0, null, $previous);
    }

    /**
     * The JSON body of Anikeen ID's error response, null without one. Some errors carry details,
     * e.g. `net_amount` of "elapsed_amount_exceeded".
     */
    public function body(): ?object
    {
        $previous = $this->getPrevious();
        $response = $previous !== null && method_exists($previous, 'getResponse') ? $previous->getResponse() : null;

        if (!$response instanceof ResponseInterface) {
            return null;
        }

        $body = json_decode((string) $response->getBody());

        return is_object($body) ? $body : null;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    /**
     * A charge was refused because it would take the user's open monthly bill above their billing
     * limit (409 "billing_limit_reached"). body() has limit, used, remaining, remaining_net,
     * required, currency and resets_at.
     */
    public function isBillingLimitReached(): bool
    {
        return $this->status === 409 && $this->error === 'billing_limit_reached';
    }

    /**
     * An order item asked for more units of an app product than are in stock (409
     * "out_of_stock"; see AnikeenId::inventory()). Nothing was created. body() has product_id,
     * sku, requested and available.
     */
    public function isOutOfStock(): bool
    {
        return $this->status === 409 && $this->error === 'out_of_stock';
    }

    /**
     * An order item named a price-bound product (Buchpreisbindung, `price_bound`) of the app's own
     * catalog at another price than its catalog price, e.g. from an outdated copy (422
     * "price_changed"). Nothing was created. body() has the first product's product_id, sku, price
     * and gross_price (the catalog prices), and all refused items in `items` (index, product_id,
     * sku, price, gross_price): update the copy, show the new price, then order again (or leave the
     * price out).
     */
    public function isPriceChanged(): bool
    {
        return $this->status === 422 && $this->error === 'price_changed';
    }

    /**
     * Anikeen ID refused to sell a catalog service as asked (422 with a PricingError code in
     * `error`): an unknown, unavailable or non-recurring product, or a coupon that doesn't apply.
     * From AppInventory::price(), subscriptions and checkout sessions with `product_id`, and usage
     * records with `product_id` (price() answers an unknown product with 404, isNotFound()).
     * Also from `change()` with `product_id` (there also MONTHLY_CAP_DIFFERS). $code narrows it to one code, e.g. PricingError::COUPON_EXPIRED;
     * PricingError::isCouponError($e->error) tells coupon from product problems. Nothing was
     * created.
     */
    public function isPricingError(?string $code = null): bool
    {
        return $this->status === 422
            && $this->error !== null
            && in_array($this->error, PricingError::all(), true)
            && ($code === null || $this->error === $code);
    }

    /**
     * The ID service could not be reached (network error, timeout) or failed (5xx): retry later.
     */
    public function isTemporary(): bool
    {
        return $this->status === 0 || $this->status >= 500 || $this->status === 429;
    }
}
