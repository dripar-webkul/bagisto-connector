<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Mockery;
use Tests\TestCase;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Bagisto\Helpers\Exporters\Product\Exporter;
use Webkul\Bagisto\Repositories\AttributeMappingRepository;
use Webkul\Bagisto\Repositories\BagistoDataMapping;
use Webkul\Bagisto\Repositories\CredentialRepository;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Repositories\ChannelRepository;
use Webkul\DataTransfer\Helpers\Sources\Export\ProductSource;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;
use Webkul\Product\Repositories\ProductRepository;

class SkuMappingTest extends TestCase
{
    private Exporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new Exporter(
            Mockery::mock(JobTrackBatchRepository::class),
            Mockery::mock(FlatItemBuffer::class),
            Mockery::mock(BagistoDataMapping::class),
            Mockery::mock(AttributeRepository::class),
            Mockery::mock(ProductRepository::class),
            Mockery::mock(CategoryRepository::class),
            Mockery::mock(AttributeOptionRepository::class),
            Mockery::mock(AttributeMappingRepository::class),
            Mockery::mock(ChannelRepository::class),
            Mockery::mock(CredentialRepository::class),
            Mockery::mock(ProductSource::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mapSkuTo(?string $unoPimAttributeCode): void
    {
        $mapped = $unoPimAttributeCode === null ? [] : ['sku' => $unoPimAttributeCode];

        $property = new \ReflectionProperty($this->exporter, 'mappingAttributes');
        $property->setAccessible(true);
        $property->setValue($this->exporter, [
            'standard_attribute' => (object) ['mapped_value' => $mapped, 'fixed_value' => []],
            'image_attribute'    => null,
        ]);
    }

    private function invoke(string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($this->exporter, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->exporter, $args);
    }

    private function product(string $sku, array $common = []): array
    {
        return [
            'id'               => 1,
            'sku'              => $sku,
            'type'             => 'simple',
            'attribute_family' => ['code' => 'default'],
            'values'           => ['common' => $common],
        ];
    }

    public function test_it_uses_the_unopim_sku_when_the_mapping_is_the_default()
    {
        $this->mapSkuTo('sku');

        $this->assertSame('SHOE-1', $this->invoke('bagistoSkuFor', [$this->product('SHOE-1')]));
    }

    public function test_it_uses_the_unopim_sku_when_nothing_is_mapped()
    {
        $this->mapSkuTo(null);

        $this->assertSame('SHOE-1', $this->invoke('bagistoSkuFor', [$this->product('SHOE-1')]));
    }

    public function test_it_uses_the_mapped_attribute_value_as_the_bagisto_sku()
    {
        $this->mapSkuTo('product_number');

        $product = $this->product('SHOE-1', ['product_number' => 'PN-9000']);

        $this->assertSame('PN-9000', $this->invoke('bagistoSkuFor', [$product]));
    }

    public function test_it_falls_back_to_the_unopim_sku_when_the_mapped_attribute_has_no_value()
    {
        $this->mapSkuTo('product_number');

        $this->assertSame('SHOE-1', $this->invoke('bagistoSkuFor', [$this->product('SHOE-1', [])]));
    }

    public function test_the_simple_product_payload_carries_the_mapped_sku()
    {
        $this->mapSkuTo('product_number');

        $payload = $this->invoke('createSimpleProductDataFormat', [
            $this->product('SHOE-1', ['product_number' => 'PN-9000']),
        ]);

        $this->assertSame('PN-9000', $payload['sku']);
    }

    public function test_the_variant_payload_points_at_the_parents_mapped_sku()
    {
        $this->mapSkuTo('product_number');

        $variant = $this->product('SHOE-1-RED', ['product_number' => 'PN-9000-RED']);
        $variant['parent'] = $this->product('SHOE-1', ['product_number' => 'PN-9000']);

        $payload = $this->invoke('createConfigurableVariantProductDataFormat', [$variant]);

        $this->assertSame('PN-9000-RED', $payload['sku']);
        $this->assertSame('PN-9000', $payload['parent_sku']);
    }

    public function test_variant_axes_reference_the_mapped_sku()
    {
        $this->mapSkuTo('product_number');

        $configurable = $this->product('SHOE-1', ['product_number' => 'PN-9000']);
        $configurable['variants'] = [
            [
                'sku'    => 'SHOE-1-RED',
                'type'   => 'simple',
                'values' => ['common' => ['product_number' => 'PN-9000-RED', 'color' => 'red']],
            ],
        ];

        $leaves = $this->invoke('collectVariantLeaves', [$configurable, ['color']]);

        $this->assertSame('PN-9000-RED', $leaves[0]['sku']);
    }

    public function test_bookkeeping_resolves_products_by_id_not_by_the_bagisto_sku()
    {
        $items = [
            ['id' => 11, 'sku' => 'PN-9000'],
            ['id' => 12, 'sku' => 'PN-9001'],
            ['id' => 11, 'sku' => 'PN-9000'],
        ];

        $this->assertSame(
            [11, 12],
            array_values($this->invoke('productIdsByBagistoSku', [$items, ['PN-9000', 'PN-9001']]))
        );
    }

    public function test_bookkeeping_skips_skus_that_were_never_sent()
    {
        $items = [['id' => 11, 'sku' => 'PN-9000']];

        $this->assertSame([], $this->invoke('productIdsByBagistoSku', [$items, ['PN-UNKNOWN']]));
    }
}
