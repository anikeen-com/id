<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Result;
use Throwable;

/**
 * Shipping labels (Deutsche Post Internetmarke). Trusted apps only, sent with the app's token.
 */
trait ManagesShippingLabels
{
    /**
     * Latest shipping labels (100 per page).
     *
     * @throws Throwable
     */
    public function shippingLabels(array $parameters = []): Result
    {
        return $this->asApp()->request('GET', 'v1/admin/shipping-labels', null, $parameters);
    }

    /**
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
     * Buy a shipping label; the postage is billed to the given user.
     *
     * @param array{
     *     box: array,
     *     weight: int,
     *     address: array,
     *     billed_user_id: string,
     *     test?: bool
     * } $attributes
     *   - box:            barcode, id, common_name or size of the box
     *   - weight:         in grams
     *   - address:        recipient address
     *   - billed_user_id: Anikeen ID of the user who pays the postage
     *   - test:           validate only, no real label is bought
     * @throws Throwable
     */
    public function issueShippingLabel(array $attributes): Result
    {
        return $this->asApp()->request('POST', 'v1/admin/shipping-labels/issue', $attributes);
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
