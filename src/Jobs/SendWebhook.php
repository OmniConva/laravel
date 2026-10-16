<?php

namespace OmniConva\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use OmniConva\Laravel\OmniConva;

/**
 * Delivers one webhook to OmniConva: Bearer token, signed body, retried with
 * backoff on network errors and 5xx/429. Other 4xx answers mean the payload
 * or credentials are wrong; retrying can't help, so the job fails at once.
 */
class SendWebhook implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $topic,
        // JSON, signed exactly as sent.
        public readonly string $body,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(OmniConva $omniconva): void
    {
        if (! $omniconva->configured()) {
            return;
        }
        // A network error throws, and the queue retries with backoff.
        $response = $omniconva->deliver($this->topic, $this->body);

        if ($response->successful()) {
            return;
        }
        $status = $response->status();
        $message = sprintf(
            'OmniConva refused the %s webhook (HTTP %d): %s',
            $this->topic,
            $status,
            OmniConva::errorMessage($response),
        );
        if ($status >= 500 || $status === 429 || $status === 408) {
            throw new \RuntimeException($message);
        }
        Log::warning($message);
        $this->fail(new \RuntimeException($message));
    }
}
