<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use OmniConva\Laravel\OmniConva;
use OmniConva\Laravel\Tests\Fixtures\FixtureProduct;

it('checks the whole setup with omniconva:test', function () {
    Http::fake(['*' => Http::response(['ok' => true, 'store' => 'store-1'])]);
    FixtureProduct::withoutEvents(fn () => FixtureProduct::create(['name' => 'Mug', 'price' => 300]));

    $this->artisan('omniconva:test')
        ->expectsOutputToContain('1 product(s) in total')
        ->expectsOutputToContain('HTTP 200, signature accepted')
        ->expectsOutputToContain('store store-1')
        ->assertSuccessful();
});

it('fails omniconva:test when something is missing', function () {
    config()->set('omniconva.orders', 'App\\Missing\\Orders');
    app()->forgetInstance(OmniConva::class);

    $this->artisan('omniconva:test', ['--offline' => true])
        ->expectsOutputToContain('Set omniconva.orders')
        ->assertFailed();
});

it('pings with omniconva:ping', function () {
    Http::fake(['*' => Http::response(['message' => 'Invalid webhook signature'], 401)]);

    $this->artisan('omniconva:ping')
        ->expectsOutputToContain('Invalid webhook signature')
        ->assertFailed();
});

it('installs: config, classes and .env', function () {
    $env = $this->app->environmentFilePath();
    $before = file_exists($env) ? file_get_contents($env) : null;
    file_put_contents($env, "APP_NAME=Shop\nOMNICONVA_WEBHOOK_URL=old\n");
    $classes = $this->app->path('OmniConva');

    try {
        $this->artisan('omniconva:install', [
            '--webhook-url' => 'https://api.omniconva.test/webhooks/custom-website/s1',
            '--token' => 'tok',
            '--secret' => 'sec ret',
            '--site-key' => 'site',
            '--force' => true,
        ])->assertSuccessful();

        expect(file_get_contents($env))
            ->toContain("APP_NAME=Shop\n")
            ->toContain("OMNICONVA_WEBHOOK_URL=https://api.omniconva.test/webhooks/custom-website/s1\n")
            ->not->toContain('=old')
            ->toContain("OMNICONVA_WEBHOOK_TOKEN=tok\n")
            ->toContain('OMNICONVA_SIGNING_SECRET="sec ret"')
            ->toContain('OMNICONVA_SITE_KEY=site');
        expect(file_get_contents($classes.'/Products.php'))->toContain('class Products extends EloquentProductSource')
            ->and(file_get_contents($classes.'/Orders.php'))->toContain('class Orders implements OrderHandler')
            ->and(config_path('omniconva.php'))->toBeFile();
    } finally {
        $before === null ? @unlink($env) : file_put_contents($env, $before);
        @unlink($classes.'/Products.php');
        @unlink($classes.'/Orders.php');
        @unlink(config_path('omniconva.php'));
    }
});

it('renders the chat widget only with a site key', function () {
    expect(Blade::render('<x-omniconva-chat />'))->toBe('');

    config()->set('omniconva.widget.site_key', 'pk_123');
    app()->forgetInstance(OmniConva::class);
    $html = Blade::render('<x-omniconva-chat />');

    expect($html)->toContain('"siteKey":"pk_123"')
        ->toContain('"apiBase":"https:\/\/api.omniconva.com"')
        ->toContain('src="https://api.omniconva.com/static/widget.min.js"')
        ->not->toContain('customer');
});

it('passes a signed customer to the widget when an identity secret is set', function () {
    config()->set('omniconva.widget.site_key', 'pk_123');
    config()->set('omniconva.widget.identity_secret', 'idsecret');
    app()->forgetInstance(OmniConva::class);

    $html = Blade::render('<x-omniconva-chat :customer="$customer" />', [
        'customer' => ['id' => 42, 'name' => 'Rahim', 'email' => null],
    ]);

    expect($html)->toContain('"id":"42"')
        ->toContain('"name":"Rahim"')
        ->not->toContain('"email"')
        ->toContain('"hash":"'.hash_hmac('sha256', '42', 'idsecret').'"');
});
