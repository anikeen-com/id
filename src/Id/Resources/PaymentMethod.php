<?php

namespace Anikeen\Id\Resources;

use Anikeen\Id\Concerns\HasBillable;

/**
 * @property string $id
 */
class PaymentMethod extends BaseResource
{
    use HasBillable;
}