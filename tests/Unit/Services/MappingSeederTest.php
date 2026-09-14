<?php

use Illuminate\Support\Facades\Config;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Tests\AttributeTestCase;
use Webkul\Bagisto\Services\MappingSeeder;

uses(AttributeTestCase::class);

beforeEach(function () {
    Config::set('bagisto-attributes', [
        ['code' => 'sku', 'type' => 'text', 'unique' => true, 'required' => true],
        ['code' => 'description', 'type' => 'textarea', 'unique' => true],
        ['code' => 'name', 'type' => 'text', 'required' => true],
        ['code' => 'status', 'type' => 'boolean', 'required' => true, 'fixedValue' => '1'],
        ['code' => 'images', 'type' => 'image,gallery,asset', 'multiple' => true],
        ['code' => 'nothing_matches_this', 'type' => 'text'],
    ]);

    Config::set('bagisto-category-fields', [
        ['code' => 'name', 'type' => 'text', 'required' => true],
        ['code' => 'position', 'type' => 'text', 'fixedValue' => '1'],
    ]);
});

it('maps bagisto fields to the unopim attribute sharing its code', function () {
    $mapping = resolve(MappingSeeder::class)->attributeDefaults();

    expect($mapping['mapped_value'])->toHaveKey('name')
        ->and($mapping['mapped_value']['name'])->toBe('name');
});

it('leaves a bagisto field unmapped when no attribute shares its code', function () {
    $mapping = resolve(MappingSeeder::class)->attributeDefaults();

    expect($mapping['mapped_value'])->not->toHaveKey('nothing_matches_this');
});

it('does not map an attribute whose type the bagisto field rejects', function () {
    Attribute::factory()->create(['code' => 'status', 'type' => 'text']);

    $mapping = resolve(MappingSeeder::class)->attributeDefaults();

    expect($mapping['mapped_value'])->not->toHaveKey('status');
});

it('accepts any of the types a multi-type field allows', function () {
    Attribute::factory()->create(['code' => 'images', 'type' => 'gallery']);

    $mapping = resolve(MappingSeeder::class)->attributeDefaults();

    expect($mapping['mapped_value']['images'])->toBe(['images']);
});

it('only maps a unique bagisto field to a unique attribute', function () {
    $mapping = resolve(MappingSeeder::class)->attributeDefaults();

    expect($mapping['mapped_value'])->toHaveKey('sku')
        ->and($mapping['mapped_value'])->not->toHaveKey('description');
});

it('carries the configured fixed values across', function () {
    $mapping = resolve(MappingSeeder::class)->attributeDefaults();

    expect($mapping['fixed_value'])->toMatchArray(['status' => '1']);
});

it('seeds category field defaults the same way', function () {
    $mapping = resolve(MappingSeeder::class)->categoryFieldDefaults();

    expect($mapping['fixed_value'])->toMatchArray(['position' => '1'])
        ->and($mapping['mapped_value'])->toHaveKey('name');
});
