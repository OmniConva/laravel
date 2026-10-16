<?php

namespace OmniConva\Laravel\Data;

use DateTimeInterface;

/**
 * A product as OmniConva sells it: the "standard" format of the OmniConva
 * Custom Website API. Build it fluently in your ProductSource::map().
 */
class ProductData
{
    private ?string $url = null;

    private ?string $description = null;

    private ?string $shortDescription = null;

    private string $status = 'active';

    private ?string $sku = null;

    private ?string $brand = null;

    /** @var list<string> */
    private array $categories = [];

    /** @var list<string> */
    private array $tags = [];

    /** @var list<string> */
    private array $images = [];

    private ?string $currency = null;

    private ?string $price = null;

    private ?string $salePrice = null;

    private ?bool $inStock = null;

    private ?int $stockQuantity = null;

    /** @var list<VariantData> */
    private array $variants = [];

    private ?DateTimeInterface $updatedAt = null;

    final public function __construct(
        private readonly string $id,
        private readonly string $title,
        int|float|string|null $price = null,
    ) {
        $this->price = Money::format($price);
    }

    public static function make(int|string $id, string $title, int|float|string|null $price = null): static
    {
        return new static((string) $id, $title, $price);
    }

    public function url(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function shortDescription(?string $text): static
    {
        $this->shortDescription = $text;

        return $this;
    }

    /** 'active' is for sale; 'draft', 'archived' or anything else is not. */
    public function status(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function active(bool $active = true): static
    {
        return $this->status($active ? 'active' : 'draft');
    }

    public function sku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function brand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    /** @param  iterable<string|null>  $categories */
    public function categories(iterable $categories): static
    {
        $this->categories = self::strings($categories);

        return $this;
    }

    /** @param  iterable<string|null>  $tags */
    public function tags(iterable $tags): static
    {
        $this->tags = self::strings($tags);

        return $this;
    }

    /** @param  iterable<string|null>  $urls  Absolute http(s) URLs; others are dropped by OmniConva. */
    public function images(iterable $urls): static
    {
        $this->images = self::strings($urls);

        return $this;
    }

    public function currency(?string $currency): static
    {
        $this->currency = $currency;

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

    /** @param  iterable<VariantData>  $variants */
    public function variants(iterable $variants): static
    {
        $this->variants = [];
        foreach ($variants as $variant) {
            $this->variants[] = $variant;
        }

        return $this;
    }

    public function updatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return array<string, mixed> */
    public function toArray(?string $defaultCurrency = null): array
    {
        $options = [];
        foreach ($this->variants as $variant) {
            foreach ($variant->toArray()['options'] as $name => $value) {
                $options[$name] ??= [];
                if (! in_array($value, $options[$name], true)) {
                    $options[$name][] = $value;
                }
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'description' => $this->description,
            'short_description' => $this->shortDescription,
            'status' => $this->status,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'images' => $this->images,
            'currency' => $this->currency ?? $defaultCurrency,
            'price' => $this->price,
            'sale_price' => $this->salePrice,
            'in_stock' => $this->inStock ?? ($this->stockQuantity !== null ? $this->stockQuantity > 0 : null),
            'stock_quantity' => $this->stockQuantity,
            'options' => array_map(
                fn (string $name, array $values) => ['name' => $name, 'values' => $values],
                array_keys($options),
                array_values($options),
            ),
            'variants' => array_map(fn (VariantData $variant) => $variant->toArray(), $this->variants),
            'updated_at' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }

    /** @return list<string> */
    private static function strings(iterable $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '' && ! in_array((string) $value, $out, true)) {
                $out[] = (string) $value;
            }
        }

        return $out;
    }
}
