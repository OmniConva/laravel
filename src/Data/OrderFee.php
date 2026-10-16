<?php

namespace OmniConva\Laravel\Data;

class OrderFee
{
    public function __construct(
        public readonly string $name,
        public readonly string $amount,
    ) {}
}
