<?php

namespace OmniConva\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use OmniConva\Laravel\Facades\OmniConva;
use Throwable;

/**
 * For a variant (or price, or stock) model whose product uses
 * SyncsWithOmniConva: any change to it re-sends the parent product.
 *
 * Best effort, like SyncsWithOmniConva: a failure to map or queue the
 * webhook is reported, never thrown out of your save.
 */
trait SyncsParentWithOmniConva
{
    public static function bootSyncsParentWithOmniConva(): void
    {
        $resend = function ($model) {
            try {
                $parent = $model->omniConvaParent();
                if ($parent instanceof Model) {
                    OmniConva::productUpdated($parent->fresh() ?? $parent);
                }
            } catch (Throwable $error) {
                report($error);
            }
        };
        static::saved($resend);
        static::deleted($resend);
    }

    /** The product this model belongs to; `$this->product` by default. */
    public function omniConvaParent(): ?Model
    {
        return $this->product;
    }
}
