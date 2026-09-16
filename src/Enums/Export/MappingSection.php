<?php

namespace Webkul\Bagisto\Enums\Export;

enum MappingSection: string
{
    case STANDARD_ATTRIBUTE = 'standard_attribute';

    case ADDITIONAL_ATTRIBUTE = 'additional_attribute';

    case IMAGE_ATTRIBUTE = 'image_attribute';

    case STANDARD_FIELD = 'standard_field';
}
