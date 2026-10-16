<?php

namespace OmniConva\Laravel\Data;

/**
 * One sellable option of a product (a size, a colour). Its id must be unique
 * and stable: orders name the variant by it.
 */
class VariantData
{
    private ?string $sku = null;

    private ?string $price = null;

    private ?string $salePrice = null;

    private ?bool $inStock = null;

    private ?int $stockQuantity = null;

    private ?string $image = null;

    /** @var array<string, string> */
    private array $options = [];

    final public function __construct(
        private readonly string $id,
        private readonly ?string $title = null,
        int|float|string|null $price = null,
    ) {
        $this->price = Money::format($price);
    }

    public static function make(int|string $id, ?string $title = null, int|float|string|null $price = null): static
    {
        return new static((string) $id, $title, $price);
    }

    public function sku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function price(int|float|string|null $price): static
    {
        $this->price = Money::format($price);

        return $this;
    }

    /** Only counts when below the price. */
    public function salePrice(int|float|string|null $salePrice): static
    {
        $this->salePrice = Money::format($salePrice);

        return $this;
    }

    public function inStock(?bool $inStock): static
    {
        $this->inStock = $inStock;

        return $this;
    }

    /** Null when you don't count stock; `inStock()` then says in or out. */
    public function stockQuantity(?int $quantity): static
    {
        $this->stockQuantity = $quantity;

        return $this;
    }

    public function image(?string $url): static
    {
        $this->image = $url;

        return $this;
    }

    /** @param  array<string, string|int|float>  $options  e.g. ['Size' => 'M', 'Color' => 'Red'] */
    public function options(array $options): static
    {
        $this->options = array_map(fn ($value) => (string) $value, $options);

        return $this;
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ?? ($this->options ? implode(' / ', $this->options) : null),
            'sku' => $this->sku,
            'price' => $this->price,
            'sale_price' => $this->salePrice,
            'in_stock' => $this->inStock ?? ($this->stockQuantity !== null ? $this->stockQuantity > 0 : null),
            'stock_quantity' => $this->stockQuantity,
            'image' => $this->image,
            'options' => (object) $this->options,
        ];
    }
}
