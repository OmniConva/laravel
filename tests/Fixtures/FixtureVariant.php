<?php

namespace OmniConva\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OmniConva\Laravel\Concerns\SyncsParentWithOmniConva;

class FixtureVariant extends Model
{
    use SyncsParentWithOmniConva;

    protected $table = 'product_variants';

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(FixtureProduct::class, 'product_id');
    }
}
