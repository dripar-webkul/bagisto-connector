<?php

namespace Webkul\Bagisto\Enums\Export;

enum SkipScope: string
{
    case PRODUCT = 'product';

    case MEDIA = 'media';

    public static function of(?string $scope): self
    {
        return self::tryFrom((string) $scope) ?? self::PRODUCT;
    }

    public function countsAsError(): bool
    {
        return $this === self::PRODUCT;
    }
}
