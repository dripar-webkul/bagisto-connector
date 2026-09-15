<?php

namespace Webkul\Bagisto\Support;

use Webkul\Bagisto\Enums\Export\ProductFilter as BagistoProductFilter;
use Webkul\DataTransfer\Helpers\Formatters\ScopeFilterValue;

class ScopeFilters
{
    public static function channelCodes(array $filters): array
    {
        return self::codes($filters, BagistoProductFilter::CHANNEL);
    }

    public static function localeCodes(array $filters): array
    {
        return self::codes($filters, BagistoProductFilter::LOCALE);
    }

    public static function categoryCodes(array $filters): array
    {
        return self::codes($filters, BagistoProductFilter::CATEGORY_CODES);
    }

    public static function typeCodes(array $filters): array
    {
        return self::codes($filters, BagistoProductFilter::TYPE);
    }

    protected static function codes(array $filters, BagistoProductFilter $filter): array
    {
        $codes = ScopeFilterValue::toCodes($filters[$filter->value] ?? null);

        if ($codes !== []) {
            return $codes;
        }

        $coreName = BagistoProductFilter::CORE_SCOPE_NAMES[$filter->value] ?? null;

        return $coreName === null ? [] : ScopeFilterValue::toCodes($filters[$coreName] ?? null);
    }
}
