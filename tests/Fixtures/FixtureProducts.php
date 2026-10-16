<?php

namespace OmniConva\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Data\VariantData;
use OmniConva\Laravel\Sources\EloquentProductSource;

class FixtureProducts extends EloquentProductSource
{
    public function query(): Builder
    {
        return FixtureProduct::query()->where('active', true)->with('variants');
    }

    public function map(Model $product): ProductData
    {
        return ProductData::make($product->id, $product->name, $product->price)
            ->salePrice($product->sale_price)
            ->stockQuantity($product->stock)
            ->images(['https://cdn.shop.test/'.$product->id.'.jpg'])
            ->variants($product->variants->map(
                fn (FixtureVariant $variant) => VariantData::make($variant->id, $variant->size, $variant->price)
                    ->stockQuantity($variant->stock)
                    ->options(['Size' => $variant->size])
            ))
            ->updatedAt($product->updated_at);
    }
}
