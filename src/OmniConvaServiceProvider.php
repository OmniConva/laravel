<?php

namespace OmniConva\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use OmniConva\Laravel\Console\InstallCommand;
use OmniConva\Laravel\Console\PingCommand;
use OmniConva\Laravel\Console\TestCommand;
use OmniConva\Laravel\Idempotency\IdempotencyStore;
use OmniConva\Laravel\View\Components\ChatWidget;

class OmniConvaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/omniconva.php', 'omniconva');

        $this->app->singleton(OmniConva::class, fn (Container $app) => new OmniConva(
            $app,
            $app->make('config'),
        ));

        $this->app->bind(IdempotencyStore::class, fn (Container $app) => new IdempotencyStore(
            $app->make(ConnectionResolverInterface::class)->connection(),
            (string) $app->make('config')->get('omniconva.idempotency_table', 'omniconva_idempotency_keys'),
        ));
    }

    public function boot(): void
    {
        $config = $this->app->make('config');

        if ($config->get('omniconva.routes.enabled', true)) {
            $prefix = trim((string) $config->get('omniconva.routes.prefix', 'omniconva'), '/');
            // An empty prefix would register /products, /orders and /health at
            // the root of the app, shadowing whatever lives there.
            if ($prefix === '') {
                throw new \InvalidArgumentException('omniconva.routes.prefix (OMNICONVA_ROUTE_PREFIX) must not be empty');
            }
            Route::prefix($prefix)
                ->middleware((array) $config->get('omniconva.routes.middleware', ['api']))
                ->group(__DIR__.'/../routes/omniconva.php');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'omniconva');
        Blade::component('omniconva-chat', ChatWidget::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/omniconva.php' => $this->app->configPath('omniconva.php'),
            ], 'omniconva-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'omniconva-migrations');

            $this->commands([
                InstallCommand::class,
                TestCommand::class,
                PingCommand::class,
            ]);
        }
    }
}
