<?php

namespace OmniConva\Laravel\Sources;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OmniConva\Laravel\Contracts\ProductSource;
use OmniConva\Laravel\Data\ProductData;
use OmniConva\Laravel\Data\ProductPage;

/**
 * A ProductSource over an Eloquent query: say which products to share and
 * how one maps to ProductData; paging, lookups and "changed since" are done
 * here. The same map() is used for the webhooks SyncsWithOmniConva sends.
 */
abstract class EloquentProductSource implements ProductSource
{
    /** The products OmniConva may see (eager-load what map() reads). */
    abstract public function query(): Builder;

    abstract public function map(Model $product): ProductData;

    /** Column for "changed since"; null if the table has none. */
    protected ?string $updatedAtColumn = 'updated_at';

    public function paginate(int $page, int $perPage, ?DateTimeInterface $since = null): ProductPage
    {
        $query = $this->query();
        $model = $query->getModel();
        if ($since !== null && $this->updatedAtColumn !== null) {
            $query->where($model->qualifyColumn($this->updatedAtColumn), '>', $since);
        }
        $total = (clone $query)->toBase()->getCountForPagination();
        $rows = $query
            ->orderBy($model->getQualifiedKeyName())
            ->forPage($page, $perPage)
            ->get();

        return new ProductPage(
            products: $rows->map(fn (Model $row) => $this->map($row))->values()->all(),
            page: $page,
            perPage: $perPage,
            total: $total,
            hasMore: $page * $perPage < $total,
        );
    }

    public function find(string $id): ?ProductData
    {
        $row = $this->query()->whereKey($id)->first();

        return $row ? $this->map($row) : null;
    }

    /** Whether a model is one this source shares (the webhook traits ask). */
    public function includes(Model $product): bool
    {
        return $this->query()->whereKey($product->getKey())->exists();
    }
}
