<?php

namespace OmniConva\Laravel\Concerns;

use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Facades\OmniConva;

/**
 * Keeps OmniConva's copy of this product current: saving sends
 * product.updated, deleting sends product.deleted. Webhooks are queued and
 * sent after the database commits, so a rolled-back save sends nothing.
 *
 * The product is mapped with your ProductSource's map(); override
 * toOmniConvaProduct() to map it differently.
 */
trait SyncsWithOmniConva
{
    public static function bootSyncsWithOmniConva(): void
    {
        static::saved(function ($model) {
            if ($model->shouldSyncToOmniConva()) {
                OmniConva::productUpdated($model);
            }
        });

        static::deleted(function ($model) {
            // Soft deletes count: OmniConva should stop selling it either way.
            if ($model->shouldSyncToOmniConva()) {
                OmniConva::productDeleted($model->getKey());
            }
        });
    }

    /** Return false to keep a product (or a change to it) away from OmniConva. */
    public function shouldSyncToOmniConva(): bool
    {
        return true;
    }

    public function toOmniConvaProduct(): ?ProductData
    {
        return OmniConva::mapProduct($this);
    }
}
