<?php

namespace Anikeen\Id\Concerns;

use Anikeen\Id\Result;
use Throwable;

trait ManagesProducts
{
    /**
     * Public product catalogue.
     *
     * @throws Throwable
     */
    public function products(): Result
    {
        return $this->request('GET', 'v1/products');
    }

    /**
     * Shipping boxes (for shipping labels).
     *
     * @throws Throwable
     */
    public function boxes(): Result
    {
        return $this->request('GET', 'v1/boxes');
    }
}
