<?php

namespace OmniConva\Laravel\Data;

class OrderItem
{
    public function __construct(
        // Your product id. For a variant, the id of its product.
        public readonly string $productId,
        // Your variant id, or null for a product without variants.
        public readonly ?string $variantId,
        public readonly ?string $sku,
        public readonly string $title,
        public readonly int $quantity,
        public readonly string $unitPrice,
        public readonly string $total,
        public readonly ?string $image,
    ) {}

    /** @param  array<string, mixed>  $item */
    public static function fromPayload(array $item): ?self
    {
        $productId = OrderData::str($item['product_id'] ?? null);
        if ($productId === null) {
            return null;
        }
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unit = Money::format($item['unit_price'] ?? null) ?? '0.00';

        return new self(
            productId: $productId,
            variantId: OrderData::str($item['variant_id'] ?? null),
            sku: OrderData::str($item['sku'] ?? null),
            title: OrderData::str($item['title'] ?? null) ?? '',
            quantity: $quantity,
            unitPrice: $unit,
            total: Money::format($item['total'] ?? null) ?? Money::format((float) $unit * $quantity),
            image: OrderData::str($item['image'] ?? null),
        );
    }
}
