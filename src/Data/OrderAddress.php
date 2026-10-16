<?php

namespace OmniConva\Laravel\Data;

class OrderAddress
{
    public function __construct(
        public readonly string $address,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postcode,
        // ISO 3166-1 alpha-2, e.g. "BD".
        public readonly ?string $country,
    ) {}
}
