<?php

use OmniConva\Laravel\Data\OrderData;
use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Data\VariantData;

it('builds a product in the standard format', function () {
    $data = ProductData::make(1001, 'Hero Hoodie', 1500)
        ->url('https://shop.test/p/hero')
        ->salePrice('1,200')
        ->brand('Hero')
        ->categories(['Hoodies', null, 'Hoodies', 'Winter'])
        ->images(['https://cdn.shop.test/a.jpg', ''])
        ->variants([
            VariantData::make('1001-M', null, 1500)->stockQuantity(7)->options(['Size' => 'M', 'Color' => 'Red']),
            VariantData::make('1001-L', 'Large')->price('1500')->inStock(false)->options(['Size' => 'L', 'Color' => 'Red']),
        ])
        ->toArray('BDT');

    expect($data)->toMatchArray([
        'id' => '1001',
        'title' => 'Hero Hoodie',
        'status' => 'active',
        'price' => '1500.00',
        'sale_price' => '1200.00',
        'currency' => 'BDT',
        'categories' => ['Hoodies', 'Winter'],
        'images' => ['https://cdn.shop.test/a.jpg'],
        'in_stock' => null,
        'options' => [
            ['name' => 'Size', 'values' => ['M', 'L']],
            ['name' => 'Color', 'values' => ['Red']],
        ],
    ])
        ->and($data['variants'][0])->toMatchArray([
            'id' => '1001-M',
            'title' => 'M / Red',
            'stock_quantity' => 7,
            'in_stock' => true,
        ])
        ->and($data['variants'][1])->toMatchArray(['title' => 'Large', 'in_stock' => false, 'stock_quantity' => null]);
});

it('derives in-stock from the quantity and marks drafts', function () {
    expect(ProductData::make(1, 'A')->stockQuantity(0)->active(false)->toArray())
        ->toMatchArray(['in_stock' => false, 'stock_quantity' => 0, 'status' => 'draft', 'price' => null]);
});

it('reads the order OmniConva sends', function () {
    $order = OrderData::fromPayload(json_decode(file_get_contents(__DIR__.'/../Fixtures/order.json'), true));

    expect($order->id)->toBe('6a3f0c1e-2b7d-4c1a-9f3e-8d2b1c0a9e77')
        ->and($order->number)->toBe('1042')
        ->and($order->test)->toBeFalse()
        ->and($order->customer->name)->toBe('Rahim Uddin')
        ->and($order->shippingAddress->city)->toBe('Dhaka')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items[0]->productId)->toBe('1001')
        ->and($order->items[0]->variantId)->toBe('1001-M')
        ->and($order->items[0]->quantity)->toBe(2)
        ->and($order->items[0]->unitPrice)->toBe('1200.00')
        ->and($order->shipping?->amount)->toBe('60.00')
        ->and($order->total)->toBe('2460.00')
        ->and($order->paymentMethod)->toBe('cash_on_delivery');
});

it('refuses an order without an id or items', function (array $payload) {
    OrderData::fromPayload($payload);
})->with([
    [['items' => [['product_id' => 1]]]],
    [['id' => 'x', 'items' => []]],
    [['id' => 'x', 'items' => [['title' => 'no product id']]]],
])->throws(InvalidArgumentException::class);
