<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use OmniConva\Laravel\Idempotency\IdempotencyStore;
use OmniConva\Laravel\OmniConva;
use OmniConva\Laravel\Tests\Fixtures\FixtureOrders;
use OmniConva\Laravel\Tests\Fixtures\FixtureProduct;

beforeEach(fn () => Http::fake());

function product(array $attributes = []): FixtureProduct
{
    return FixtureProduct::create(array_merge(['name' => 'Mug', 'price' => 300, 'stock' => 5], $attributes));
}

function orderPayload(array $overrides = []): array
{
    return array_merge(json_decode(file_get_contents(__DIR__.'/../Fixtures/order.json'), true), $overrides);
}

it('refuses unsigned or wrongly signed requests', function () {
    $this->getJson('/omniconva/products')->assertStatus(401);
    $this->signedGet('/omniconva/products', 'wrong-secret')->assertStatus(401);
    $this->call('GET', '/omniconva/products', [], [], [], [
        'HTTP_X_OMNICONVA_SIGNATURE' => $this->signer()->signRequest('GET', '/omniconva/products', '', time() - 1000),
    ])->assertStatus(401);
});

it('serves products page by page in the standard format', function () {
    foreach (range(1, 3) as $i) {
        product(['name' => "Mug {$i}"]);
    }
    product(['name' => 'Hidden', 'active' => false]);

    $this->signedGet('/omniconva/products?page=1&per_page=2')
        ->assertOk()
        ->assertJson(['page' => 1, 'per_page' => 2, 'total' => 3, 'has_more' => true])
        ->assertJsonPath('products.0.title', 'Mug 1')
        ->assertJsonPath('products.0.price', '300.00')
        ->assertJsonPath('products.0.currency', 'BDT');

    $this->signedGet('/omniconva/products?page=2&per_page=2')
        ->assertOk()
        ->assertJson(['has_more' => false])
        ->assertJsonCount(1, 'products');
});

it('only serves what changed since a date when asked', function () {
    Carbon::setTestNow('2026-10-01 10:00:00');
    product(['name' => 'Old']);
    Carbon::setTestNow('2026-10-10 10:00:00');
    product(['name' => 'New']);
    Carbon::setTestNow();

    $this->signedGet('/omniconva/products?updated_since=2026-10-05T00%3A00%3A00.000Z')
        ->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('products.0.title', 'New');
});

it('serves one product, with its variants, or 404', function () {
    $tee = product(['name' => 'Tee', 'price' => null, 'stock' => null]);
    $tee->variants()->create(['size' => 'M', 'price' => 650, 'stock' => 3]);

    $this->signedGet("/omniconva/products/{$tee->id}")
        ->assertOk()
        ->assertJsonPath('variants.0.options.Size', 'M')
        ->assertJsonPath('variants.0.in_stock', true)
        ->assertJsonPath('options.0.name', 'Size');

    $this->signedGet('/omniconva/products/999')->assertNotFound();
    $hidden = product(['active' => false]);
    $this->signedGet("/omniconva/products/{$hidden->id}")->assertNotFound();
});

it('answers the health check', function () {
    $this->signedGet('/omniconva/health')
        ->assertOk()
        ->assertJson(['package' => 'omniconva/laravel', 'version' => '1.0.0'])
        ->assertJsonPath('framework', fn ($value) => str_starts_with($value, 'Laravel '));
});

it('creates an order once per idempotency key', function () {
    $payload = orderPayload();
    $headers = ['Idempotency-Key' => $payload['id']];

    $this->signedPost('/omniconva/orders', $payload, $headers)
        ->assertCreated()
        ->assertExactJson(['order_id' => 'W-1001', 'order_number' => '5001']);

    $this->signedPost('/omniconva/orders', $payload, $headers)
        ->assertOk()
        ->assertExactJson(['order_id' => 'W-1001', 'order_number' => '5001']);

    expect(FixtureOrders::$created)->toHaveCount(1)
        ->and(FixtureOrders::$created[0]->customer->phone)->toBe('01711000000');
});

it('tells OmniConva to retry while the same order is still being created', function () {
    $payload = orderPayload();
    app(IdempotencyStore::class)->claim($payload['id']);

    $this->signedPost('/omniconva/orders', $payload, ['Idempotency-Key' => $payload['id']])
        ->assertStatus(503)
        ->assertHeader('Retry-After', '30');
    expect(FixtureOrders::$created)->toBeEmpty();
});

it('reports a refused order and lets a retry try again', function () {
    $payload = orderPayload(['customer' => ['name' => 'Refuse Me', 'phone' => '01700000000']]);

    $this->signedPost('/omniconva/orders', $payload)
        ->assertStatus(422)
        ->assertJson(['message' => 'Out of delivery area']);

    $payload['customer']['name'] = 'Rahim';
    $this->signedPost('/omniconva/orders', $payload)->assertCreated();
    expect(FixtureOrders::$created)->toHaveCount(1);
});

it('answers test orders without calling your handler, unless told to', function () {
    $this->signedPost('/omniconva/orders', orderPayload(['test' => true]))
        ->assertCreated()
        ->assertJson(['test' => true]);
    expect(FixtureOrders::$created)->toBeEmpty();

    config()->set('omniconva.pass_test_orders', true);
    app()->forgetInstance(OmniConva::class);
    $this->signedPost('/omniconva/orders', orderPayload(['test' => true, 'id' => 'test-2']))->assertCreated();
    expect(FixtureOrders::$created)->toHaveCount(1);
});

it('refuses an order body it cannot read', function () {
    $this->signedPost('/omniconva/orders', 'not json')->assertStatus(422);
    $this->signedPost('/omniconva/orders', ['id' => 'x', 'items' => []])
        ->assertStatus(422)
        ->assertJson(['message' => 'The order has no items']);
});

it('moves the routes with the configured prefix', function () {
    expect(route('omniconva.products.index', [], false))->toBe('/omniconva/products');
});
