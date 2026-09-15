<?php

namespace Webkul\Bagisto\Enums;

enum CredentialTab: string
{
    case GENERAL = 'general';

    case ATTRIBUTE_MAPPING = 'attribute_mapping';

    case CATEGORY_MAPPING = 'category_mapping';

    public function routeName(): string
    {
        return match ($this) {
            self::GENERAL           => 'admin.bagisto.credentials.edit',
            self::ATTRIBUTE_MAPPING => 'admin.bagisto.credentials.attribute_mapping',
            self::CATEGORY_MAPPING  => 'admin.bagisto.credentials.category_mapping',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GENERAL           => 'bagisto::app.bagisto.credentials.tabs.credential',
            self::ATTRIBUTE_MAPPING => 'bagisto::app.bagisto.credentials.tabs.attribute-mapping',
            self::CATEGORY_MAPPING  => 'bagisto::app.bagisto.credentials.tabs.category-mapping',
        };
    }

    public function url(int|string $credentialId): string
    {
        return route($this->routeName(), $credentialId);
    }

    public static function historyUrl(int|string $credentialId): string
    {
        return route(self::GENERAL->routeName(), ['id' => $credentialId, 'history' => 1]);
    }

    public static function items(int|string $credentialId): array
    {
        return array_map(fn (self $tab): array => [
            'key'   => $tab->value,
            'url'   => $tab->url($credentialId),
            'label' => $tab->label(),
        ], self::cases());
    }
}
