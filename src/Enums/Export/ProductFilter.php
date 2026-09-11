<?php

namespace Webkul\Bagisto\Enums\Export;

enum ProductFilter: string
{
    case CREDENTIALS = 'credentials';

    case CHANNEL = 'channel';

    case LOCALE = 'locale';

    case TYPE = 'type';

    case CODE = 'code';

    case WITH_MEDIA = 'with_media';

    case WITH_ASSOCIATIONS = 'with_associations';

    public static function connectorFields(): array
    {
        return array_column([
            self::CREDENTIALS,
            self::CHANNEL,
            self::LOCALE,
            self::TYPE,
            self::CODE,
        ], 'value');
    }
}
