<?php

namespace Webkul\Bagisto\Enums\Export;

enum DamFileType: string
{
    case IMAGE = 'image';

    case VIDEO = 'video';

    case DOCUMENT = 'document';

    case AUDIO = 'audio';

    public static function isImage(?string $fileType): bool
    {
        return $fileType === self::IMAGE->value;
    }
}
