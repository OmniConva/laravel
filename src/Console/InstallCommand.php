<?php

namespace OmniConva\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use OmniConva\Laravel\Exceptions\OmniConvaException;
use OmniConva\Laravel\OmniConva;

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
        if ($this->option('token') !== null || $this->option('secret') !== null) {
            $this->components->warn('Secrets passed as options end up in your shell history; prefer typing them at the prompt.');
        }
        // What is in place now comes from config(), not env(): with the
        // config cached (as in production) env() reads nothing, and "Enter
        // to keep" would have blanked every credential.
        $current = fn (string $key, string $configKey) => (string) (config($configKey) ?? '');
        $webhookUrl = $this->option('webhook-url') ?? $this->ask('Webhook URL', $current('OMNICONVA_WEBHOOK_URL', 'omniconva.webhook_url'));
        if (filled($webhookUrl)) {
            try {
                OmniConva::assertSecureUrl('OMNICONVA_WEBHOOK_URL', (string) $webhookUrl);
            } catch (OmniConvaException $error) {
                $this->components->error($error->getMessage());

                return self::FAILURE;
            }
        }
        $values = [
            'OMNICONVA_WEBHOOK_URL' => $webhookUrl,
            'OMNICONVA_WEBHOOK_TOKEN' => $this->option('token') ?? ($this->secret('Webhook token (hidden; Enter to keep)') ?: $current('OMNICONVA_WEBHOOK_TOKEN', 'omniconva.webhook_token')),
            'OMNICONVA_SIGNING_SECRET' => $this->option('secret') ?? ($this->secret('Signing secret (hidden; Enter to keep)') ?: $current('OMNICONVA_SIGNING_SECRET', 'omniconva.signing_secret')),
            'OMNICONVA_SITE_KEY' => $this->option('site-key') ?? $this->ask('Chat widget Site Key (optional)', $current('OMNICONVA_SITE_KEY', 'omniconva.widget.site_key')),
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
            if (preg_match('/[\r\n]/', $value)) {
                throw new \InvalidArgumentException($key.' must be a single line');
            }
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $exists = (bool) preg_match($pattern, $contents);
            // Nothing typed and nothing known: leave whatever is in .env alone
            // rather than replacing it with an empty assignment.
            if ($value === '' && $exists) {
                continue;
            }
            // Always quoted: a value starting with $ or containing # or spaces
            // would otherwise be expanded or cut short by the .env parser.
            $line = $key.'="'.addcslashes($value, '"\\$').'"';
            $contents = $exists
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents)
                : rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
        }
        $files->put($path, $contents);
    }
}
