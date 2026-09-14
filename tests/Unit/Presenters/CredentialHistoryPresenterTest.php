<?php

use Webkul\Bagisto\Models\Credential;
use Webkul\Bagisto\Presenters\CredentialPresenter;

it('masks password values with a fixed width mask', function () {
    $result = CredentialPresenter::representValueForHistory('secret123', 'a-much-longer-new-secret', 'password');

    expect($result)->toMatchArray([
        'password' => [
            'name' => 'password',
            'old'  => Credential::MASKED_PASSWORD,
            'new'  => Credential::MASKED_PASSWORD,
        ],
    ]);
});

it('does not leak the stored password length through the mask', function () {
    $short = CredentialPresenter::representValueForHistory('a', 'b', 'password');
    $long = CredentialPresenter::representValueForHistory(str_repeat('x', 88), str_repeat('y', 120), 'password');

    expect($short['password']['old'])->toBe($long['password']['old']);
});

it('reports no change when the password is untouched', function () {
    expect(CredentialPresenter::representValueForHistory('same', 'same', 'password'))->toBe([]);
});

it('renders filterable attributes using the labels stored alongside the ids', function () {
    $old = json_encode([[
        'filterableAttribtes'      => '23,24',
        'filterableAttribteLabels' => ['23' => 'Color', '24' => 'Size'],
    ]]);

    $new = json_encode([[
        'filterableAttribtes'      => '23',
        'filterableAttribteLabels' => ['23' => 'Color'],
    ]]);

    $result = CredentialPresenter::representValueForHistory($old, $new, 'additional_info');

    expect($result)->toMatchArray([
        'filterableAttribtes' => [
            'name' => 'filterableAttribtes',
            'old'  => 'Color, Size',
            'new'  => 'Color',
        ],
    ]);
});

it('falls back to the raw id when no label was stored for it', function () {
    $old = json_encode([['filterableAttribtes' => '23,24']]);
    $new = json_encode([[
        'filterableAttribtes'      => '23,24',
        'filterableAttribteLabels' => ['23' => 'Color'],
    ]]);

    $result = CredentialPresenter::representValueForHistory($old, $new, 'additional_info');

    expect($result)->toBe([]);
});

it('does not resolve bagisto attribute ids against unopim attributes', function () {
    $old = json_encode([['filterableAttribtes' => '23']]);
    $new = json_encode([['filterableAttribtes' => '24']]);

    $result = CredentialPresenter::representValueForHistory($old, $new, 'additional_info');

    expect($result['filterableAttribtes']['old'])->toBe('23')
        ->and($result['filterableAttribtes']['new'])->toBe('24');
});
