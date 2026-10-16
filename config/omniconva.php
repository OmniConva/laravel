<?php

use App\OmniConva\Orders;
use App\OmniConva\Products;

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | From OmniConva: Settings > Connection > Custom website > Laravel package.
    | The signing secret proves requests to your routes come from OmniConva
    | and signs the webhooks you send back; the token authorises them.
    |
    */

    'webhook_url' => env('OMNICONVA_WEBHOOK_URL'),

    'webhook_token' => env('OMNICONVA_WEBHOOK_TOKEN'),

    'signing_secret' => env('OMNICONVA_SIGNING_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Your classes
    |--------------------------------------------------------------------------
    |
    | `products` implements OmniConva\Laravel\Contracts\ProductSource (usually
    | by extending Sources\EloquentProductSource); `orders` implements
    | Contracts\OrderHandler. `php artisan omniconva:install` creates both.
    |
    */

    'products' => Products::class,

    'orders' => Orders::class,

    // Sent with products that don't name their own currency.
    'currency' => env('OMNICONVA_CURRENCY', 'BDT'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | OmniConva calls GET {prefix}/products, GET {prefix}/products/{id},
    | POST {prefix}/orders and GET {prefix}/health. If you change the prefix,
    | change "Route prefix" in OmniConva to match.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => env('OMNICONVA_ROUTE_PREFIX', 'omniconva'),
        'middleware' => ['api'],
    ],

    // How far a request's signature timestamp may be from now, in seconds.
    'signature_tolerance' => 300,

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    |
    | Test orders sent from the OmniConva dashboard are answered without
    | calling your OrderHandler unless this is true.
    |
    */

    'pass_test_orders' => false,

    'idempotency_table' => 'omniconva_idempotency_keys',

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Product and order-status webhooks are sent from a queued job (after the
    | database commits) and retried. Null uses your default connection/queue.
    |
    */

    'queue' => [
        'connection' => env('OMNICONVA_QUEUE_CONNECTION'),
        'name' => env('OMNICONVA_QUEUE'),
    ],

    'webhook_timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | Chat widget
    |--------------------------------------------------------------------------
    |
    | <x-omniconva-chat /> renders the chat widget when a site key is set
    | (OmniConva: Settings > Custom Chat). With an identity secret, signed-in
    | users are recognised by the AI and agents.
    |
    */

    'widget' => [
        'site_key' => env('OMNICONVA_SITE_KEY'),
        'api_url' => env('OMNICONVA_API_URL', 'https://api.omniconva.com'),
        'identity_secret' => env('OMNICONVA_IDENTITY_SECRET'),
    ],

];
