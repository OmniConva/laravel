<?php

namespace OmniConva\Laravel\Data;

/**
 * What your OrderHandler returns: your id for the new order (OmniConva shows
 * it to the customer and names the order by it in order-status updates) and,
 * optionally, the number your store shows.
 */
class OrderResult
{
    final public function __construct(
        public readonly string $orderId,
        public readonly ?string $orderNumber = null,
    ) {}

    public static function make(int|string $orderId, int|string|null $orderNumber = null): static
    {
        return new static((string) $orderId, $orderNumber === null ? null : (string) $orderNumber);
    }

    /** @return array{order_id: string, order_number: ?string} */
    public function toArray(): array
    {
        return ['order_id' => $this->orderId, 'order_number' => $this->orderNumber];
    }
}
