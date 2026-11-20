<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Result;
use Throwable;

/**
 * Shipping labels (Deutsche Post Internetmarke). Trusted apps only, sent with the app's token. An
 * app only ever sees the labels it issued itself: any other label answers 404.
 */
trait ManagesShippingLabels
{
    /**
     * The app's shipping labels, newest first, 100 per page.
     *
     * @param array{
     *     page?: int,
     *     reference?: string
     * } $parameters
     *   - page:      the page, from 1
     *   - reference: only the labels issued under this reference, voided ones included
     * @throws Throwable
     */
    public function shippingLabels(array $parameters = []): Result
    {
        return $this->asApp()->request('GET', 'v1/admin/shipping-labels', null, $parameters);
    }

    /**
     * One of the app's shipping labels; 404 for anyone else's.
     *
     * @throws Throwable
     */
    public function shippingLabel(string $id): Result
    {
        return $this->asApp()->request('GET', sprintf('v1/admin/shipping-labels/%s', $id));
    }

    /**
     * The label as PDF (binary string), null if there is none.
     *
     * @throws Throwable
     */
    public function shippingLabelPdf(string $id): ?string
    {
        $result = $this->asApp()->request('GET', sprintf('v1/admin/shipping-labels/%s/pdf', $id));

        return $result->success() ? (string) $result->response()->getBody() : null;
    }

    /**
     * Buy a shipping label; the postage is billed to the given user. `201` with the label bought,
     * `200` with the one already issued under the same `reference`.
     *
     * @param array{
     *     box: array,
     *     weight: int,
     *     address: array,
     *     billed_user_id: string,
     *     test?: bool,
     *     reference?: string
     * } $attributes
     *   - box:            barcode, id, common_name or size of the box
     *   - weight:         in grams
     *   - address:        recipient address
     *   - billed_user_id: Anikeen ID of the user who pays the postage
     *   - test:           a dry run: checked like a purchase, but nothing is bought or billed; the
     *                     label has `provider: test` and a placeholder PDF
     *   - reference:      the app's own ID for the label (up to 100 characters), e.g. its envelope's.
     *                     A retry with it gets the live label already issued instead of a second
     *                     stamp; a different purchase under it answers 409 `reference_conflict`
     *                     (with `label_id`), and 409 `label_busy` while one with it is still running.
     *                     Voiding the label frees the reference.
     * @throws Throwable
     */
    public function issueShippingLabel(array $attributes): Result
    {
        return $this->asApp()->request('POST', 'v1/admin/shipping-labels/issue', $attributes);
    }

    /**
     * Void a label that will not be posted: an Internetmarke stamp is refunded through Deutsche
     * Post's retoure, a dry run label is only marked. `200` with the label; 409 `label_cancelled`,
     * `label_shipped`, `label_not_refundable` or `label_busy`, 502 `refund_failed` (nothing changed,
     * try again) otherwise, as `{message, error}`.
     *
     * @throws Throwable
     */
    public function voidShippingLabel(string $id): Result
    {
        return $this->asApp()->request('POST', sprintf('v1/admin/shipping-labels/%s/void', $id));
    }

    /**
     * Mark the label with the scanned matrix code as shipped.
     *
     * @param list<string> $hex Hex bytes of the scanned Internetmarke matrix code
     * @throws Throwable
     */
    public function shipShippingLabel(array $hex): Result
    {
        return $this->asApp()->request('POST', 'v1/admin/shipping-labels/ship', ['hex' => $hex]);
    }

    /**
     * Prices of the available franking types.
     *
     * @throws Throwable
     */
    public function shippingPriceList(): Result
    {
        return $this->asApp()->request('GET', 'v1/admin/shipping-labels/price-list');
    }
}
