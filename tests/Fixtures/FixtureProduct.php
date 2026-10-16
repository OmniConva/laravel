<?php

namespace OmniConva\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OmniConva\Laravel\Concerns\SyncsWithOmniConva;

class FixtureProduct extends Model
{
    use SoftDeletes;
    use SyncsWithOmniConva;

    protected $table = 'products';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];

    public function variants(): HasMany
    {
        return $this->hasMany(FixtureVariant::class, 'product_id');
    }
}
