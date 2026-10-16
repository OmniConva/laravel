<?php

namespace OmniConva\Laravel\Contracts;

use OmniConva\Laravel\Data\OrderData;
use OmniConva\Laravel\Data\OrderResult;

/**
 * Creates an order in your store. Called once per order: a retry with the
 * same Idempotency-Key is answered by the package with the first result.
 * Throw an exception to refuse the order; its message is shown to the store
 * owner in OmniConva's connection logs.
 */
interface OrderHandler
{
    public function createOrder(OrderData $order): OrderResult;
}
