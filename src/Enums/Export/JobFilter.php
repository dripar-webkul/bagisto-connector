<?php

namespace Webkul\Bagisto\Enums\Export;

enum JobFilter: string
{
    case WITH_MEDIA = 'withMedia';

    case WITH_ASSOCIATIONS = 'withAssociations';

    case CHANNEL = 'channel';

    case LOCALES = 'locales';
}
