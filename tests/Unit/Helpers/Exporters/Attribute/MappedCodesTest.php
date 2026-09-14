<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Attribute;

use Mockery;
use Tests\TestCase;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Bagisto\Helpers\Exporters\Attribute\Exporter;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\BagistoDataMapping;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;

class MappedCodesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function flatten(array $mappedValue): array
    {
        $exporter = new Exporter(
            Mockery::mock(JobTrackBatchRepository::class),
            Mockery::mock(FlatItemBuffer::class),
            Mockery::mock(BagistoDataMapping::class),
            Mockery::mock(AttributeRepository::class),
            Mockery::mock(AttributeMappingRepository::class),
            Mockery::mock(CredentialRepository::class),
        );

        $method = new \ReflectionMethod($exporter, 'flattenMappedCodes');
        $method->setAccessible(true);

        return $method->invoke($exporter, $mappedValue);
    }

    public function test_it_flattens_a_mapping_that_holds_several_codes_for_one_bagisto_field()
    {
        $this->assertSame(['sku', 'image', 'product_gallery', 'name'], $this->flatten([
            'sku'    => 'sku',
            'images' => ['image', 'product_gallery'],
            'name'   => 'name',
        ]));
    }

    public function test_it_reads_a_single_code_mapping_unchanged()
    {
        $this->assertSame(['sku'], $this->flatten(['sku' => 'sku']));
    }

    public function test_it_drops_unmapped_fields()
    {
        $this->assertSame(['sku'], $this->flatten([
            'sku'   => 'sku',
            'brand' => null,
            'cost'  => '',
        ]));
    }

    public function test_it_returns_nothing_for_an_empty_mapping()
    {
        $this->assertSame([], $this->flatten([]));
    }
}
