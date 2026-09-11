<?php

use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Tests\AttributeTestCase;
use Webkul\Bagisto\Presenters\CredentialPresenter;

uses(AttributeTestCase::class);

it('masks password values in credential history', function () {
    $result = CredentialPresenter::representValueForHistory('secret123', 'newsecret', 'password');

    expect($result)->toMatchArray([
        'password' => [
            'name' => 'password',
            'old'  => '*********',
            'new'  => '*********',
        ],
    ]);
});

it('renders filterable attribute ids as names in credential history', function () {
    $brand = Attribute::factory()->create(['code' => 'bagisto_brand_'.uniqid(), 'type' => 'text', 'name' => 'Brand']);
    $color = Attribute::factory()->create(['code' => 'bagisto_color_'.uniqid(), 'type' => 'text', 'name' => 'Color']);

    $old = json_encode([['filterableAttribtes' => $brand->id.','.$color->id]]);
    $new = json_encode([['filterableAttribtes' => (string) $brand->id]]);

    $result = CredentialPresenter::representValueForHistory($old, $new, 'additional_info');

    expect($result)->toMatchArray([
        'filterableAttribtes' => [
            'name' => 'filterableAttribtes',
            'old'  => 'Brand, Color',
            'new'  => 'Brand',
        ],
    ]);
});
