<?php

use Tests\TestCase;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Bagisto\Helpers\Exporters\Attribute\Exporter;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\BagistoDataMapping;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;

uses(TestCase::class);

function attributeExporterFlatten(array $mappedValue): array
{
    $exporter = new Exporter(
        Mockery::mock(JobTrackBatchRepository::class),
        Mockery::mock(FlatItemBuffer::class),
        Mockery::mock(BagistoDataMapping::class),
        Mockery::mock(AttributeRepository::class),
        Mockery::mock(AttributeMappingRepository::class),
        Mockery::mock(CredentialRepository::class),
    );

    $method = new ReflectionMethod($exporter, 'flattenMappedCodes');
    $method->setAccessible(true);

    return $method->invoke($exporter, $mappedValue);
}

afterEach(fn () => Mockery::close());

it('flattens a mapping that holds several codes for one bagisto field', function () {
    expect(attributeExporterFlatten([
        'sku'    => 'sku',
        'images' => ['image', 'product_gallery'],
        'name'   => 'name',
    ]))->toBe(['sku', 'image', 'product_gallery', 'name']);
});

it('reads a single code mapping unchanged', function () {
    expect(attributeExporterFlatten(['sku' => 'sku']))->toBe(['sku']);
});

it('drops unmapped fields', function () {
    expect(attributeExporterFlatten([
        'sku'   => 'sku',
        'brand' => null,
        'cost'  => '',
    ]))->toBe(['sku']);
});

it('returns nothing for an empty mapping', function () {
    expect(attributeExporterFlatten([]))->toBe([]);
});
