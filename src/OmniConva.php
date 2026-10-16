<?php

namespace OmniConva\Laravel;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use OmniConva\Laravel\Contracts\OrderHandler;
use OmniConva\Laravel\Contracts\ProductSource;
use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Data\Tracking;
use OmniConva\Laravel\Exceptions\OmniConvaException;
use OmniConva\Laravel\Jobs\SendWebhook;
use OmniConva\Laravel\Signature\Signer;

/**
 * The package's entry point (facade: OmniConva). Sends webhooks to
 * OmniConva and resolves your ProductSource and OrderHandler.
 */
class OmniConva
{
    public const VERSION = '1.0.1';

    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    /** The package configuration, or one key of it. */
    public function config(?string $key = null): mixed
    {
        return $this->config->get($key === null ? 'omniconva' : 'omniconva.'.$key);
    }

    /** @internal What an error response says, for log and exception messages. */
    public static function errorMessage(Response $response): string
    {
        $message = $response->json('message');
        if (is_array($message)) {
            $message = implode('; ', array_map('strval', $message));
        }

        return is_string($message) && $message !== '' ? $message : substr($response->body(), 0, 300);
    }

    /** Whether the webhook URL, token and signing secret are all set. */
    public function configured(): bool
    {
        return filled($this->config('webhook_url'))
            && filled($this->config('webhook_token'))
            && filled($this->config('signing_secret'));
    }

    /**
     * The webhook URL has to be https: the token and every payload travel
     * with it. Plain http is accepted for a local machine, or anywhere when
     * OMNICONVA_ALLOW_HTTP=1 opts a private network (Docker, a LAN) in.
     *
     * @internal
     */
    public static function assertSecureUrl(string $name, string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme === 'https') {
            return;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $local = in_array($host, ['localhost', '[::1]'], true)
            || str_starts_with($host, '127.')
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
        $allowed = $local || filter_var(env('OMNICONVA_ALLOW_HTTP', config('omniconva.allow_http', false)), FILTER_VALIDATE_BOOL);
        if (! ($scheme === 'http' && $allowed)) {
            throw new OmniConvaException($name.' must start with https:// (http:// is allowed for localhost, or anywhere with OMNICONVA_ALLOW_HTTP=1)');
        }
    }

    public function signer(): Signer
    {
        return new Signer(
            (string) ($this->config('signing_secret') ?? ''),
            (int) ($this->config('signature_tolerance') ?? 300),
        );
    }

    public function products(): ProductSource
    {
        return $this->resolve('products', ProductSource::class);
    }

    public function orders(): OrderHandler
    {
        return $this->resolve('orders', OrderHandler::class);
    }

    public function currency(): ?string
    {
        return $this->config('currency');
    }

    /**
     * A model as OmniConva sees it: read back through your ProductSource, so
     * it is mapped (and filtered) exactly as the Products API serves it.
     * Null when the source doesn't share this product.
     */
    public function mapProduct(Model $product): ?ProductData
    {
        return $this->products()->find((string) $product->getKey());
    }

    /**
     * Tells OmniConva a product changed. A model your ProductSource doesn't
     * share (filtered out by its query) is sent as deleted, so OmniConva
     * stops selling it.
     */
    public function productUpdated(Model|ProductData $product): void
    {
        if (! $this->configured()) {
            return;
        }
        $data = $product instanceof ProductData
            ? $product
            : (method_exists($product, 'toOmniConvaProduct') ? $product->toOmniConvaProduct() : $this->mapProduct($product));

        if ($data === null) {
            $this->productDeleted($product instanceof Model ? $product->getKey() : '');

            return;
        }
        $this->send('product.updated', $data->toArray($this->currency()));
    }

    public function productDeleted(int|string|Model $product): void
    {
        $id = $product instanceof Model ? $product->getKey() : $product;
        if (! $this->configured() || $id === '' || $id === null) {
            return;
        }
        $this->send('product.deleted', ['id' => (string) $id]);
    }

    /**
     * Where an order is now, so OmniConva's AI can answer "where is my
     * order?". `$orderId` is your id, as your OrderHandler returned it.
     * Statuses are your own words: pending, confirmed, processing, shipped,
     * delivered, cancelled, returned, ...
     */
    public function orderUpdated(int|string $orderId, string $status, ?Tracking $tracking = null, ?string $note = null): void
    {
        if (! $this->configured()) {
            return;
        }
        $this->send('order.updated', array_filter([
            'order_id' => (string) $orderId,
            'status' => $status,
            'tracking' => $tracking?->toArray(),
            'note' => $note,
            'updated_at' => Carbon::now()->toAtomString(),
        ], fn ($value) => $value !== null));
    }

    /**
     * Checks the webhook URL, token and signing secret with OmniConva, right
     * now (not queued).
     *
     * @return array<string, mixed>
     *
     * @throws OmniConvaException
     */
    public function ping(): array
    {
        if (! $this->configured()) {
            throw new OmniConvaException('Set OMNICONVA_WEBHOOK_URL, OMNICONVA_WEBHOOK_TOKEN and OMNICONVA_SIGNING_SECRET first.');
        }
        $response = $this->deliver('ping', '{}');

        if (! $response->successful()) {
            throw new OmniConvaException(sprintf(
                'OmniConva answered HTTP %d: %s',
                $response->status(),
                self::errorMessage($response),
            ));
        }

        return (array) $response->json();
    }

    /**
     * Posts one webhook to OmniConva now: Bearer token, X-Webhook-Topic and
     * a signature over exactly this body. Throws on network errors.
     */
    public function deliver(string $topic, string $body): Response
    {
        $url = (string) $this->config('webhook_url');
        self::assertSecureUrl('OMNICONVA_WEBHOOK_URL', $url);

        return Http::timeout((int) ($this->config('webhook_timeout') ?? 10))
            ->withoutRedirecting()
            ->withToken((string) $this->config('webhook_token'))
            ->withHeaders([
                'X-Webhook-Topic' => $topic,
                'X-OmniConva-Signature' => $this->signer()->signWebhook($body),
                'User-Agent' => 'omniconva-laravel/'.self::VERSION,
            ])
            ->withBody($body, 'application/json')
            ->post($url);
    }

    /** Queues one webhook, sent after the current database transaction commits. @param  array<string, mixed>  $payload */
    public function send(string $topic, array $payload): void
    {
        // A description in a legacy encoding loses its bad bytes rather than
        // failing the whole product (and, from a model event, the save).
        $job = new SendWebhook($topic, json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        ));
        $queue = $this->config('queue') ?? [];
        if (! empty($queue['connection'])) {
            $job->onConnection($queue['connection']);
        }
        if (! empty($queue['name'])) {
            $job->onQueue($queue['name']);
        }
        $this->container->make(Dispatcher::class)->dispatch($job->afterCommit());
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function resolve(string $key, string $contract): object
    {
        $class = $this->config($key);
        if (! is_string($class) || ! class_exists($class)) {
            throw new OmniConvaException(sprintf(
                'Set omniconva.%s to your %s class (php artisan omniconva:install creates one).',
                $key,
                class_basename($contract),
            ));
        }
        $instance = $this->container->make($class);
        if (! $instance instanceof $contract) {
            throw new OmniConvaException(sprintf('%s must implement %s.', $class, $contract));
        }

        return $instance;
    }
}
