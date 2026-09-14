<?php

use Tests\TestCase;
use Webkul\Bagisto\Enums\CredentialTab;

uses(TestCase::class);

it('lists every tab once, in order', function () {
    expect(array_column(CredentialTab::items(1), 'key'))
        ->toBe(['general', 'attribute_mapping', 'category_mapping']);
});

it('points each tab at its own page for the credential', function () {
    $urls = array_column(CredentialTab::items(7), 'url', 'key');

    expect($urls['general'])->toEndWith('/credentials/edit/7')
        ->and($urls['attribute_mapping'])->toEndWith('/credentials/7/attribute-mapping')
        ->and($urls['category_mapping'])->toEndWith('/credentials/7/category-mapping');
});

it('gives every tab a translatable label', function () {
    foreach (CredentialTab::items(1) as $tab) {
        expect($tab['label'])->toStartWith('bagisto::app.bagisto.credentials.tabs.');
    }
});
it('sends history to the credential page rather than the current tab', function () {
    expect(CredentialTab::historyUrl(7))->toEndWith('/credentials/edit/7?history=1');
});

it('uses one history url no matter which tab asked for it', function () {
    $fromAnyTab = array_map(fn () => CredentialTab::historyUrl(7), CredentialTab::cases());

    expect(array_unique($fromAnyTab))->toHaveCount(1);
});

it('keeps one credential history apart from another', function () {
    expect(CredentialTab::historyUrl(7))->not->toBe(CredentialTab::historyUrl(8));
});
