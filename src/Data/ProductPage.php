<?php

namespace OmniConva\Laravel\Data;

/** One page of the catalog, as ProductSource::paginate() returns it. */
class ProductPage
{
    /** @param  list<ProductData>  $products */
    public function __construct(
        public readonly array $products,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?int $total,
        public readonly bool $hasMore,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(?string $defaultCurrency = null): array
    {
        return [
            'products' => array_map(fn (ProductData $product) => $product->toArray($defaultCurrency), $this->products),
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'has_more' => $this->hasMore,
        ];
    }
}
