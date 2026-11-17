<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Resources\AppInventory;

trait ManagesInventory
{
    /**
     * The app's own product catalog and stock, with the app's own token (scope inventory:client,
     * any app): products, stock movements and inventory counts. Orders name the products with
     * `items.*.product_id`; Anikeen ID reserves and takes their stock along the order.
     */
    public function inventory(): AppInventory
    {
        return new AppInventory();
    }
}
