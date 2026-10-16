<?php

namespace OmniConva\Laravel\Data;

/** Delivery tracking for OmniConva::orderUpdated(). */
class Tracking
{
    final public function __construct(
        public readonly ?string $number = null,
        public readonly ?string $carrier = null,
        public readonly ?string $url = null,
    ) {}

    public static function make(?string $number = null, ?string $carrier = null, ?string $url = null): static
    {
        return new static($number, $carrier, $url);
    }

    /** @return array{number: ?string, carrier: ?string, url: ?string} */
    public function toArray(): array
    {
        return ['number' => $this->number, 'carrier' => $this->carrier, 'url' => $this->url];
    }
}
