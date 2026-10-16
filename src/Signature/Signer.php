<?php

namespace OmniConva\Laravel\Signature;

/**
 * OmniConva's HMAC-SHA256 signatures, header format "t=<unix>,v1=<hex>".
 *
 * Requests from OmniConva to your routes sign "<t>.<METHOD>.<path?query>.<body>";
 * webhooks you send to OmniConva sign "<t>.<body>".
 */
class Signer
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300,
    ) {}

    public function signWebhook(string $body, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return sprintf('t=%d,v1=%s', $timestamp, $this->hmac("{$timestamp}.{$body}"));
    }

    public function signRequest(string $method, string $pathAndQuery, string $body, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $method = strtoupper($method);

        return sprintf(
            't=%d,v1=%s',
            $timestamp,
            $this->hmac("{$timestamp}.{$method}.{$pathAndQuery}.{$body}"),
        );
    }

    public function verifyRequest(?string $header, string $method, string $pathAndQuery, string $body, ?int $now = null): bool
    {
        $parts = $this->parse($header);
        if ($parts === null) {
            return false;
        }
        [$timestamp, $provided] = $parts;
        if (abs(($now ?? time()) - $timestamp) > $this->tolerance) {
            return false;
        }
        $method = strtoupper($method);
        $expected = $this->hmac("{$timestamp}.{$method}.{$pathAndQuery}.{$body}");

        return hash_equals($expected, $provided);
    }

    /** @return array{0: int, 1: string}|null */
    private function parse(?string $header): ?array
    {
        if ($this->secret === '' || $header === null || $header === '') {
            return null;
        }
        $values = [];
        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);
            if (count($pair) === 2) {
                $values[trim($pair[0])] = trim($pair[1]);
            }
        }
        if (! isset($values['t'], $values['v1']) || ! ctype_digit($values['t'])) {
            return null;
        }

        return [(int) $values['t'], $values['v1']];
    }

    private function hmac(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
