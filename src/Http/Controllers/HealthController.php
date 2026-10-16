<?php

namespace OmniConva\Laravel\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use OmniConva\Laravel\OmniConva;

/** GET {prefix}/health: OmniConva's "Check package" button reads this. */
class HealthController
{
    public function __invoke(Application $app): JsonResponse
    {
        return new JsonResponse([
            'package' => 'omniconva/laravel',
            'version' => OmniConva::VERSION,
            'framework' => 'Laravel '.$app->version(),
            'php' => PHP_VERSION,
        ]);
    }
}
