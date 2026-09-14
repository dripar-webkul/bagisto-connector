<?php

use Webkul\Bagisto\Enums\Export\CacheType;

it('keeps one credential apart from another', function () {
    expect(CacheType::ATTRIBUTE_MAPPING->forCredential(1))
        ->not->toBe(CacheType::ATTRIBUTE_MAPPING->forCredential(2));
});

it('falls back to a stable key when there is no credential', function () {
    expect(CacheType::ATTRIBUTE_MAPPING->forCredential(null))
        ->toBe(CacheType::ATTRIBUTE_MAPPING->forCredential(null));
});

it('keeps each cache type in its own key', function () {
    $keys = array_map(fn (CacheType $type) => $type->forCredential(1), CacheType::cases());

    expect(array_unique($keys))->toHaveCount(count(CacheType::cases()));
});

it('caches nothing that a credential update cannot reach', function () {
    $invalidated = [
        CacheType::CREDENTIAL,
        CacheType::BAGISTO_API_HTTP,
        CacheType::ATTRIBUTE_MAPPING,
        CacheType::CATEGORY_FIELD_MAPPING,
        CacheType::ADDITIONAL_INFO,
        CacheType::UNOPIM_CATEGORY_FIELDS,
    ];

    $names = fn (array $types) => array_map(fn (CacheType $type) => $type->name, $types);

    expect(array_diff($names(CacheType::cases()), $names($invalidated)))->toBe([]);
});
