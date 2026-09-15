<?php

namespace Webkul\Bagisto\Enums\Export;

enum ProductType: string
{
    case SIMPLE = 'simple';

    case CONFIGURABLE = 'configurable';

    case VARIANT_GROUP = 'variant_group';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
