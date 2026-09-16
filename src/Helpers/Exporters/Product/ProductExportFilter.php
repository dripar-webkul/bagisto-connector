<?php

namespace Webkul\Bagisto\Helpers\Exporters\Product;

use Illuminate\Database\Eloquent\Builder;
use Webkul\Bagisto\Enums\Export\ProductStatus;
use Webkul\Bagisto\Support\CategoryScope;
use Webkul\Bagisto\Support\ScopeFilters;
use Webkul\DataTransfer\Enums\ProductExportScope;
use Webkul\DataTransfer\Enums\ProductFilter;
use Webkul\DataTransfer\Helpers\Sources\Export\Filters\ProductExportFilter as BaseProductExportFilter;

class ProductExportFilter extends BaseProductExportFilter
{
    public function applyToQuery(Builder $query, array $filters): void
    {
        parent::applyToQuery($query, $filters);

        $this->applyType($query, $filters);
    }

    public function statusValue(array $filters): ?bool
    {
        return ProductStatus::tryFrom((string) ($filters[ProductFilter::STATUS->value] ?? ''))?->toBoolean();
    }

    protected function applyType(Builder $query, array $filters): void
    {
        $types = ScopeFilters::typeCodes($filters);

        if ($types === []) {
            return;
        }

        $query->whereIn('type', $types);
    }

    protected function applyCategories(Builder $query, array $filters): void
    {
        $codes = $this->categoryCodes($filters);

        if ($codes === []) {
            return;
        }

        $scoped = CategoryScope::scopedCodes($codes, ScopeFilters::channelCodes($filters));

        if ($scoped === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($scoped): void {
            foreach ($scoped as $code) {
                $query->orWhereJsonContains('values->categories', $code);
            }
        });
    }

    protected function resolveChannelIds(array $filters): array
    {
        return parent::resolveChannelIds($this->toCoreScope($filters));
    }

    protected function resolveLocaleIds(array $filters): array
    {
        return parent::resolveLocaleIds($this->toCoreScope($filters));
    }

    protected function toCoreScope(array $filters): array
    {
        return [
            ProductExportScope::CHANNELS->value => ScopeFilters::channelCodes($filters),
            ProductExportScope::LOCALES->value  => ScopeFilters::localeCodes($filters),
        ];
    }
}
