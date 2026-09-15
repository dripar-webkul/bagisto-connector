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

class AttributeScopeTest extends TestCase
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

    private function scopeTo(array $attributeCodes): void
    {
        $method = new \ReflectionMethod($this->exporter, 'applyAttributeScope');
        $method->setAccessible(true);
        $method->invoke($this->exporter, $attributeCodes);
    }

    private function mapRequiredFieldsTo(array $mapping, string $section = 'standard_attribute'): void
    {
        $property = new \ReflectionProperty($this->exporter, 'mappingAttributes');
        $property->setAccessible(true);
        $property->setValue($this->exporter, [$section => (object) ['mapped_value' => $mapping]]);
    }

    private function narrow(array $values): array
    {
        $method = new \ReflectionMethod($this->exporter, 'scopeToSelectedAttributes');
        $method->setAccessible(true);
        $method->invokeArgs($this->exporter, [&$values]);

        return $values;
    }

    public function test_it_sends_every_value_when_no_attribute_is_selected()
    {
        $this->scopeTo([]);

        $values = ['sku' => 'SHOE-1', 'name' => 'Shoe', 'price' => '10'];

        $this->assertSame($values, $this->narrow($values));
    }

    public function test_it_sends_only_the_selected_attributes()
    {
        $this->scopeTo(['name', 'color']);

        $narrowed = $this->narrow([
            'sku'        => 'SHOE-1',
            'name'       => 'Shoe',
            'color'      => 'Black',
            'meta_title' => 'Shoe',
        ]);

        $this->assertSame(['sku', 'name', 'color'], array_keys($narrowed));
    }

    public function test_it_keeps_the_sku_even_when_it_is_not_selected()
    {
        $this->scopeTo(['name']);

        $this->assertArrayHasKey('sku', $this->narrow(['sku' => 'SHOE-1', 'name' => 'Shoe']));
    }

    public function test_it_leaves_an_unselected_attribute_out_rather_than_emptying_it()
    {
        $this->scopeTo(['name']);

        $this->assertArrayNotHasKey('meta_title', $this->narrow(['sku' => 'SHOE-1', 'meta_title' => 'Shoe']));
    }

    public function test_it_keeps_the_fields_bagisto_requires_when_they_are_not_selected()
    {
        $this->scopeTo(['image', 'name', 'sku']);

        $narrowed = $this->narrow([
            'sku'               => 'SHOE-1',
            'name'              => 'Shoe',
            'image'             => 'shoe.jpg',
            'price'             => '10',
            'short_description' => 'A shoe',
            'description'       => 'A very good shoe',
            'url_key'           => 'shoe',
            'weight'            => '2',
            'meta_title'        => 'Shoe',
        ]);

        foreach (['price', 'short_description', 'description', 'url_key', 'weight'] as $required) {
            $this->assertArrayHasKey($required, $narrowed, "{$required} is required by Bagisto");
        }

        $this->assertArrayNotHasKey('meta_title', $narrowed);
    }

    public function test_it_keeps_the_unopim_attribute_mapped_to_a_required_field()
    {
        $this->mapRequiredFieldsTo(['price' => 'selling_price', 'weight' => ['gross_weight']]);

        $this->scopeTo(['name']);

        $narrowed = $this->narrow([
            'sku'           => 'SHOE-1',
            'name'          => 'Shoe',
            'selling_price' => '10',
            'gross_weight'  => '2',
            'meta_title'    => 'Shoe',
        ]);

        $this->assertArrayHasKey('selling_price', $narrowed);
        $this->assertArrayHasKey('gross_weight', $narrowed);
        $this->assertArrayNotHasKey('meta_title', $narrowed);
    }
}
