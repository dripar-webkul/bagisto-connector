<?php

namespace Webkul\Bagisto\Enums\Export;

enum BagistoImageFormat: string
{
    case JPG = 'jpg';

    case JPEG = 'jpeg';

    case PNG = 'png';

    case GIF = 'gif';

    case WEBP = 'webp';

    case BMP = 'bmp';

    case AVIF = 'avif';

    public static function accepts(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return in_array($extension, array_column(self::cases(), 'value'), true);
    }
}
