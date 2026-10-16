<?php

namespace OmniConva\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'omniconva:install
        {--webhook-url= : OMNICONVA_WEBHOOK_URL}
        {--token= : OMNICONVA_WEBHOOK_TOKEN}
        {--secret= : OMNICONVA_SIGNING_SECRET}
        {--site-key= : OMNICONVA_SITE_KEY (chat widget)}
        {--force : Overwrite app/OmniConva classes that already exist}';

    protected $description = 'Set up the OmniConva package: config, migration, your Products and Orders classes, and .env';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Setting up OmniConva');

        $this->callSilently('vendor:publish', ['--tag' => 'omniconva-config']);
        $this->callSilently('vendor:publish', ['--tag' => 'omniconva-migrations']);
        $this->components->task('Published config/omniconva.php and the migration');

        $namespace = rtrim($this->laravel->getNamespace(), '\\').'\\OmniConva';
        $directory = $this->laravel->path('OmniConva');
        $files->ensureDirectoryExists($directory);
        foreach (['Products' => 'products', 'Orders' => 'orders'] as $class => $stub) {
            $target = $directory.DIRECTORY_SEPARATOR.$class.'.php';
            if ($files->exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail("app/OmniConva/{$class}.php", '<fg=yellow>exists, kept</>');

                continue;
            }
            $files->put($target, str_replace(
                '{{ namespace }}',
                $namespace,
                $files->get(__DIR__.'/../../stubs/'.$stub.'.stub'),
            ));
            $this->components->twoColumnDetail("app/OmniConva/{$class}.php", '<fg=green>created</>');
        }

        $this->newLine();
        $this->line('  Copy these from OmniConva: Settings → Connection → Custom website → Laravel package.');
        $values = [
            'OMNICONVA_WEBHOOK_URL' => $this->option('webhook-url') ?? $this->ask('Webhook URL', (string) env('OMNICONVA_WEBHOOK_URL', '')),
            'OMNICONVA_WEBHOOK_TOKEN' => $this->option('token') ?? ($this->secret('Webhook token (hidden; Enter to keep)') ?: env('OMNICONVA_WEBHOOK_TOKEN')),
            'OMNICONVA_SIGNING_SECRET' => $this->option('secret') ?? ($this->secret('Signing secret (hidden; Enter to keep)') ?: env('OMNICONVA_SIGNING_SECRET')),
            'OMNICONVA_SITE_KEY' => $this->option('site-key') ?? $this->ask('Chat widget Site Key (optional)', (string) env('OMNICONVA_SITE_KEY', '')),
        ];
        $this->writeEnv($files, $values);
        $this->components->task('Wrote OMNICONVA_* to .env');

        $this->newLine();
        $this->components->info('Next steps');
        $this->components->bulletList([
            'php artisan migrate',
            'Fill in app/OmniConva/Products.php (which products, how they map) and app/OmniConva/Orders.php (save an order)',
            'Add `use \\OmniConva\\Laravel\\Concerns\\SyncsWithOmniConva;` to your Product model',
            'Add <x-omniconva-chat /> to your layout for the chat widget',
            'php artisan omniconva:test, then press "Check package" in OmniConva',
        ]);

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $values */
    private function writeEnv(Filesystem $files, array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $contents = $files->exists($path) ? $files->get($path) : '';
        foreach ($values as $key => $value) {
            $value = (string) ($value ?? '');
            $line = $key.'='.(preg_match('/[\s#"\']/', $value) ? '"'.addcslashes($value, '"\\').'"' : $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents)
                : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
        }
        $files->put($path, $contents);
    }
}
