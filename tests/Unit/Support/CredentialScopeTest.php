<?php

use Webkul\Bagisto\Support\CredentialScope;

function bagistoStoreInfo(): array
{
    return [
        json_encode([
            'channel' => ['default' => 'default'],
            'locales' => ['en' => 'en_US', 'fr' => 'fr_FR'],
        ]),
        json_encode([
            'channel' => ['b2b_store' => 'b2b'],
            'locales' => ['de' => 'de_DE'],
        ]),
    ];
}

it('maps bagisto channels to the unopim channels the user picked', function () {
    expect(CredentialScope::channelMap(bagistoStoreInfo()))->toBe([
        'default'   => 'default',
        'b2b_store' => 'b2b',
    ]);
});

it('maps each bagisto channel to its locale mapping', function () {
    expect(CredentialScope::localeMap(bagistoStoreInfo()))->toBe([
        'default'   => ['en' => 'en_US', 'fr' => 'fr_FR'],
        'b2b_store' => ['de' => 'de_DE'],
    ]);
});

it('lists only the unopim channels the credential maps', function () {
    expect(CredentialScope::unopimChannelCodes(bagistoStoreInfo()))->toBe(['default', 'b2b']);
});

it('lists every mapped locale when no channel narrows the request', function () {
    expect(CredentialScope::unopimLocaleCodes(bagistoStoreInfo()))->toBe(['en_US', 'fr_FR', 'de_DE']);
});

it('narrows the locales to the channels being exported', function () {
    expect(CredentialScope::unopimLocaleCodes(bagistoStoreInfo(), ['b2b']))->toBe(['de_DE']);
});

it('offers nothing for a channel the credential does not map', function () {
    expect(CredentialScope::unopimLocaleCodes(bagistoStoreInfo(), ['unmapped']))->toBe([]);
});

it('survives a store configuration that was never filled in', function () {
    foreach ([null, [], [''], ['not json'], [json_encode(['channel' => []])]] as $storeInfo) {
        expect(CredentialScope::unopimChannelCodes($storeInfo))->toBe([])
            ->and(CredentialScope::unopimLocaleCodes($storeInfo))->toBe([]);
    }
});

it('drops a channel whose unopim side was left blank', function () {
    $storeInfo = [json_encode([
        'channel' => ['default' => ''],
        'locales' => ['en' => 'en_US'],
    ])];

    expect(CredentialScope::unopimChannelCodes($storeInfo))->toBe([]);
});

it('reads an already decoded store configuration', function () {
    $decoded = array_map(fn (string $entry) => json_decode($entry), bagistoStoreInfo());

    expect(CredentialScope::unopimChannelCodes($decoded))->toBe(['default', 'b2b']);
});
