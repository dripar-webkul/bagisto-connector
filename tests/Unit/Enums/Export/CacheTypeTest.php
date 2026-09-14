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

it('keeps two export profiles on one credential apart', function () {
    expect(CacheType::PRODUCT_JOB_FILTERS->forJob(1, 6))
        ->not->toBe(CacheType::PRODUCT_JOB_FILTERS->forJob(1, 7));
});

it('still separates credentials when the profile id matches', function () {
    expect(CacheType::PRODUCT_JOB_FILTERS->forJob(1, 6))
        ->not->toBe(CacheType::PRODUCT_JOB_FILTERS->forJob(2, 6));
});

it('never collides a job scoped key with the credential scoped one', function () {
    expect(CacheType::PRODUCT_JOB_FILTERS->forJob(1, 6))
        ->not->toBe(CacheType::PRODUCT_JOB_FILTERS->forCredential(1));
});

it('keeps product and category filters in separate keys', function () {
    expect(CacheType::PRODUCT_JOB_FILTERS->forJob(1, 6))
        ->not->toBe(CacheType::CATEGORY_JOB_FILTERS->forJob(1, 6));
});
