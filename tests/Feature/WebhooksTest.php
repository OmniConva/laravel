<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use OmniConva\Laravel\Data\Tracking;
use OmniConva\Laravel\Exceptions\OmniConvaException;
use OmniConva\Laravel\Facades\OmniConva;
use OmniConva\Laravel\Jobs\SendWebhook;
use OmniConva\Laravel\Signature\Signer;
use OmniConva\Laravel\Tests\Fixtures\FixtureProduct;
use OmniConva\Laravel\Tests\TestCase;

function sentWebhooks(): array
{
    return collect(Http::recorded())->map(fn ($pair) => $pair[0])->all();
}

function assertSignedWebhook(Request $request, string $topic): array
{
    expect($request->url())->toBe(TestCase::WEBHOOK_URL)
        ->and($request->header('Authorization')[0])->toBe('Bearer '.TestCase::TOKEN)
        ->and($request->header('X-Webhook-Topic')[0])->toBe($topic);
    $signature = $request->header('X-OmniConva-Signature')[0];
    preg_match('/t=(\d+)/', $signature, $match);
    expect($signature)->toBe((new Signer(TestCase::SECRET))->signWebhook($request->body(), (int) $match[1]));

    return json_decode($request->body(), true);
}

// A fresh fake: Http::fake() stubs added later never override earlier ones.
function fakeOmniConva(mixed $response): void
{
    Http::swap(new Factory);
    Http::fake(['*' => $response]);
}

beforeEach(fn () => fakeOmniConva(Http::response(['ok' => true])));

it('sends product.updated when a product is saved, mapped by your source', function () {
    $mug = FixtureProduct::create(['name' => 'Mug', 'price' => 300, 'stock' => 4]);

    $requests = sentWebhooks();
    expect($requests)->toHaveCount(1);
    $body = assertSignedWebhook($requests[0], 'product.updated');
    expect($body)->toMatchArray([
        'id' => (string) $mug->id,
        'title' => 'Mug',
        'price' => '300.00',
        'stock_quantity' => 4,
        'currency' => 'BDT',
    ]);
});

it('sends nothing when the save is rolled back', function () {
    try {
        DB::transaction(function () {
            FixtureProduct::create(['name' => 'Mug', 'price' => 300]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(sentWebhooks())->toBeEmpty();
});

it('sends product.deleted on delete, and for a product your source no longer shares', function () {
    $mug = FixtureProduct::create(['name' => 'Mug', 'price' => 300]);
    $mug->update(['active' => false]);
    $mug->delete();

    $requests = sentWebhooks();
    expect($requests)->toHaveCount(3);
    expect(assertSignedWebhook($requests[1], 'product.deleted'))->toBe(['id' => (string) $mug->id]);
    expect(assertSignedWebhook($requests[2], 'product.deleted'))->toBe(['id' => (string) $mug->id]);
});

it('re-sends the parent product when a variant changes', function () {
    $tee = FixtureProduct::create(['name' => 'Tee']);
    $tee->variants()->create(['size' => 'M', 'price' => 650, 'stock' => 2]);

    $requests = sentWebhooks();
    $body = assertSignedWebhook(end($requests), 'product.updated');
    expect($body['id'])->toBe((string) $tee->id)
        ->and($body['variants'][0]['options'])->toBe(['Size' => 'M']);
});

it('sends order status updates', function () {
    OmniConva::orderUpdated('W-1001', 'shipped', Tracking::make('TRK1', 'Pathao', 'https://t.test/TRK1'), 'Rider assigned');

    $body = assertSignedWebhook(sentWebhooks()[0], 'order.updated');
    expect($body)->toMatchArray([
        'order_id' => 'W-1001',
        'status' => 'shipped',
        'tracking' => ['number' => 'TRK1', 'carrier' => 'Pathao', 'url' => 'https://t.test/TRK1'],
        'note' => 'Rider assigned',
    ]);
});

it('does nothing until the credentials are set', function () {
    config()->set('omniconva.webhook_token', null);
    app()->forgetInstance(\OmniConva\Laravel\OmniConva::class);
    OmniConva::clearResolvedInstances();

    FixtureProduct::create(['name' => 'Mug']);
    OmniConva::orderUpdated('1', 'shipped');

    expect(sentWebhooks())->toBeEmpty();
});

it('retries a webhook OmniConva could not take, and gives up on one it refused', function () {
    fakeOmniConva(Http::sequence()->push(['message' => 'down'], 503)->push(['message' => ['bad id']], 400));
    $omniconva = app(\OmniConva\Laravel\OmniConva::class);

    expect(fn () => (new SendWebhook('product.updated', '{}'))->handle($omniconva))
        ->toThrow(RuntimeException::class, 'HTTP 503');

    $job = new SendWebhook('product.updated', '{}');
    $job->job = new class
    {
        public ?Throwable $failed = null;

        public function fail($error): void
        {
            $this->failed = $error;
        }
    };
    $job->handle($omniconva);
    expect($job->job->failed?->getMessage())->toContain('HTTP 400')->toContain('bad id');
});

it('pings OmniConva to check the credentials', function () {
    fakeOmniConva(Http::response(['ok' => true, 'store' => 'store-1']));

    expect(OmniConva::ping())->toBe(['ok' => true, 'store' => 'store-1']);
    assertSignedWebhook(sentWebhooks()[0], 'ping');

    fakeOmniConva(Http::response(['message' => 'Invalid webhook token'], 401));
    expect(fn () => OmniConva::ping())->toThrow(OmniConvaException::class, 'Invalid webhook token');
});
