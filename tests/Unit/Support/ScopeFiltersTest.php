<?php

use Webkul\Bagisto\Support\ScopeFilters;

it('reads the connector filter names', function () {
    $filters = [
        'channel'         => [['code' => 'default'], ['code' => 'b2b']],
        'locale'          => 'en_US,fr_FR',
        'category_codes'  => ['men'],
        'type'            => 'simple',
    ];

    expect(ScopeFilters::channelCodes($filters))->toBe(['default', 'b2b'])
        ->and(ScopeFilters::localeCodes($filters))->toBe(['en_US', 'fr_FR'])
        ->and(ScopeFilters::categoryCodes($filters))->toBe(['men'])
        ->and(ScopeFilters::typeCodes($filters))->toBe(['simple']);
});

it('falls back to the equivalent core filter name', function () {
    $filters = [
        'channels'   => ['default'],
        'locales'    => 'en_US,fr_FR',
        'categories' => ['men'],
    ];

    expect(ScopeFilters::channelCodes($filters))->toBe(['default'])
        ->and(ScopeFilters::localeCodes($filters))->toBe(['en_US', 'fr_FR'])
        ->and(ScopeFilters::categoryCodes($filters))->toBe(['men']);
});

it('prefers the connector filter name over the core one', function () {
    $filters = [
        'channel'  => 'default',
        'channels' => ['b2b'],
    ];

    expect(ScopeFilters::channelCodes($filters))->toBe(['default']);
});

it('reports no selection when a filter is absent or empty', function () {
    foreach ([[], ['channel' => null], ['channel' => ''], ['channel' => []]] as $filters) {
        expect(ScopeFilters::channelCodes($filters))->toBe([]);
    }

    expect(ScopeFilters::categoryCodes([]))->toBe([])
        ->and(ScopeFilters::typeCodes([]))->toBe([]);
});
