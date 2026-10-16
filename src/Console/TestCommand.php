<?php

namespace OmniConva\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use OmniConva\Laravel\OmniConva;
use Throwable;

class TestCommand extends Command
{
    protected $signature = 'omniconva:test {--offline : Skip the call to OmniConva}';

    protected $description = 'Check your OmniConva setup: credentials, product mapping, routes and the order handler';

    private bool $failed = false;

    public function handle(OmniConva $omniconva, Kernel $kernel): int
    {
        $this->components->info('Checking OmniConva');

        $this->check('Credentials in .env', function () use ($omniconva) {
            if (! $omniconva->configured()) {
                throw new \RuntimeException('set OMNICONVA_WEBHOOK_URL, OMNICONVA_WEBHOOK_TOKEN and OMNICONVA_SIGNING_SECRET');
            }

            return 'set';
        });

        $this->check('Products class', function () use ($omniconva) {
            $page = $omniconva->products()->paginate(1, 5);
            foreach ($page->products as $product) {
                $data = $product->toArray();
                if ($data['id'] === '' || $data['title'] === '') {
                    throw new \RuntimeException('a product has no id or title');
                }
            }

            return sprintf('%d product(s) in total, first page reads fine', $page->total ?? count($page->products));
        });

        $this->check('Orders class', fn () => get_class($omniconva->orders()));

        $routes = (array) $omniconva->config('routes');
        $prefix = '/'.trim((string) ($routes['prefix'] ?? 'omniconva'), '/');
        foreach (['/health' => 'Route GET '.$prefix.'/health', '/products?page=1&per_page=2' => 'Route GET '.$prefix.'/products'] as $path => $label) {
            $this->check($label, function () use ($omniconva, $kernel, $prefix, $path) {
                $uri = $prefix.$path;
                $request = Request::create($uri, 'GET');
                $request->headers->set('X-OmniConva-Signature', $omniconva->signer()->signRequest('GET', $uri, ''));
                $response = $kernel->handle($request);
                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('HTTP '.$response->getStatusCode().': '.substr((string) $response->getContent(), 0, 200));
                }

                return 'HTTP 200, signature accepted';
            });
        }

        if (! $this->option('offline')) {
            $this->check('OmniConva accepts the token and signature', function () use ($omniconva) {
                $result = $omniconva->ping();

                return isset($result['store']) ? 'store '.$result['store'] : 'ok';
            });
        }

        $this->newLine();
        if ($this->failed) {
            $this->components->error('Fix the items above, then run this again.');

            return self::FAILURE;
        }
        $this->components->info('All good. In OmniConva, press "Check package", then "Connect website".');

        return self::SUCCESS;
    }

    private function check(string $label, callable $run): void
    {
        try {
            $this->components->twoColumnDetail($label, '<fg=green>'.$run().'</>');
        } catch (Throwable $error) {
            $this->failed = true;
            $this->components->twoColumnDetail($label, '<fg=red>'.$error->getMessage().'</>');
        }
    }
}
