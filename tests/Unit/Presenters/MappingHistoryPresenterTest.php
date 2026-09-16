<?php

use Webkul\Bagisto\Presenters\JsonDataPresenter;

it('renders a mapping that mixes plain values with a list', function () {
    $old = json_encode(['sku' => 'sku', 'name' => 'name', 'images' => ['image']]);
    $new = json_encode(['sku' => 'sku', 'name' => 'length', 'images' => ['image']]);

    expect(JsonDataPresenter::representValueForHistory($old, $new, 'mapped_value'))->toBe([
        'name' => ['name' => 'name', 'old' => 'name', 'new' => 'length'],
    ]);
});

it('shows a list value as readable text', function () {
    $old = json_encode(['images' => ['image']]);
    $new = json_encode(['images' => ['image', 'image_2']]);

    $history = JsonDataPresenter::representValueForHistory($old, $new, 'mapped_value');

    expect($history['images']['old'])->toBe('image')
        ->and($history['images']['new'])->toBe('image, image_2');
});

it('reports nothing when the mapping did not change', function () {
    $mapping = json_encode(['sku' => 'sku', 'images' => ['image']]);

    expect(JsonDataPresenter::representValueForHistory($mapping, $mapping, 'mapped_value'))->toBe([]);
});

it('keeps every added attribute apart', function () {
    $old = json_encode([
        ['code' => 'a', 'name' => 'A', 'type' => 'text'],
        ['code' => 'b', 'name' => 'B', 'type' => 'multiselect'],
    ]);

    $new = json_encode([
        ['code' => 'a', 'name' => 'A', 'type' => 'text'],
        ['code' => 'b', 'name' => 'B', 'type' => 'multiselect'],
        ['code' => 'c', 'name' => 'C', 'type' => 'date'],
    ]);

    $history = JsonDataPresenter::representValueForHistory($old, $new, 'mapped_value');

    expect(array_keys($history))->toBe(['c'])
        ->and($history['c']['new'])->toBe('name: C, type: date');
});

it('renders a fixed value that was cleared', function () {
    $old = json_encode(['status' => '1', 'inventories' => '10']);
    $new = json_encode(['status' => '1', 'inventories' => null]);

    $history = JsonDataPresenter::representValueForHistory($old, $new, 'fixed_value');

    expect($history['inventories']['old'])->toBe('10')
        ->and($history['inventories']['new'])->toBe('');
});

it('reports nothing when both versions are empty', function () {
    expect(JsonDataPresenter::representValueForHistory(null, null, 'mapped_value'))->toBe([])
        ->and(JsonDataPresenter::representValueForHistory('', '', 'fixed_value'))->toBe([]);
});
