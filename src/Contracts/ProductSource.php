<?php

namespace OmniConva\Laravel\Contracts;

use DateTimeInterface;
use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Data\ProductPage;

/**
 * Your catalog, as OmniConva reads it. Most stores extend
 * OmniConva\Laravel\Sources\EloquentProductSource instead of implementing
 * this directly.
 */
interface ProductSource
{
    /** One page, in a stable order. `$since` asks for only what changed after it. */
    public function paginate(int $page, int $perPage, ?DateTimeInterface $since = null): ProductPage;

    /** One product by its id, or null when it doesn't exist (or isn't shared). */
    public function find(string $id): ?ProductData;
}
