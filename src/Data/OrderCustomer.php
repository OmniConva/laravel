<?php

namespace OmniConva\Laravel\Data;

class OrderCustomer
{
    public function __construct(
        public readonly string $name,
        public readonly string $phone,
    ) {}
}
