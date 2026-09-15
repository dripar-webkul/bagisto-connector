<?php

namespace Webkul\Bagisto\Enums\Export;

enum ProductFilter: string
{
    case CREDENTIALS = 'credentials';

    case CHANNEL = 'channel';

    case LOCALE = 'locale';

    case ATTRIBUTE_CODES = 'attribute_codes';

    case TYPE = 'type';

    case CODE = 'code';

    case CATEGORY_CODES = 'category_codes';

    case WITH_MEDIA = 'with_media';

    case WITH_ASSOCIATIONS = 'with_associations';

    public const CORE_SCOPE_NAMES = [
        self::CHANNEL->value         => 'channels',
        self::LOCALE->value          => 'locales',
        self::ATTRIBUTE_CODES->value => 'attributes',
        self::CATEGORY_CODES->value  => 'categories',
    ];

    public static function connectorFields(): array
    {
        return array_merge(self::credentialFields(), self::scopeFields(), self::filterFields());
    }

    public static function credentialFields(): array
    {
        return array_column([
            self::CREDENTIALS,
            self::TYPE,
        ], 'value');
    }

    public static function scopeFields(): array
    {
        return array_column([
            self::CHANNEL,
            self::LOCALE,
            self::ATTRIBUTE_CODES,
        ], 'value');
    }

    public static function filterFields(): array
    {
        return array_column([
            self::CODE,
            self::CATEGORY_CODES,
        ], 'value');
    }

    public static function scopedByCredential(): array
    {
        return array_column([
            self::CHANNEL,
            self::LOCALE,
        ], 'value');
    }

    public static function reservedCoreNames(): array
    {
        return array_values(self::CORE_SCOPE_NAMES);
    }
}
