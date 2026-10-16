<?php

namespace OmniConva\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use OmniConva\Laravel\OmniConvaServiceProvider;
use OmniConva\Laravel\Signature\Signer;
use OmniConva\Laravel\Tests\Fixtures\FixtureOrders;
use OmniConva\Laravel\Tests\Fixtures\FixtureProducts;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const SECRET = 'test-secret';

    public const TOKEN = 'test-token';

    public const WEBHOOK_URL = 'https://api.omniconva.test/webhooks/custom-website/store-1';

    protected function getPackageProviders($app): array
    {
        return [OmniConvaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('omniconva.webhook_url', self::WEBHOOK_URL);
        $app['config']->set('omniconva.webhook_token', self::TOKEN);
        $app['config']->set('omniconva.signing_secret', self::SECRET);
        $app['config']->set('omniconva.products', FixtureProducts::class);
        $app['config']->set('omniconva.orders', FixtureOrders::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('size');
            $table->decimal('price', 12, 2);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        FixtureOrders::reset();
    }

    public function signer(): Signer
    {
        return new Signer(self::SECRET);
    }

    public function signedGet(string $uri, ?string $secret = null): TestResponse
    {
        $signature = (new Signer($secret ?? self::SECRET))->signRequest('GET', $uri, '');

        return $this->call('GET', $uri, [], [], [], ['HTTP_X_OMNICONVA_SIGNATURE' => $signature, 'HTTP_ACCEPT' => 'application/json']);
    }

    /** @param  array<string, mixed>|string  $body */
    public function signedPost(string $uri, array|string $body, array $headers = []): TestResponse
    {
        $raw = is_string($body) ? $body : json_encode($body);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_OMNICONVA_SIGNATURE' => $this->signer()->signRequest('POST', $uri, $raw),
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $raw);
    }
}
