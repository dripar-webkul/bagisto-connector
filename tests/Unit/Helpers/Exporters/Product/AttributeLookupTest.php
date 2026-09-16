<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
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

class AttributeLookupTest extends TestCase
{
    private Exporter $exporter;

    private $attributeRepository;

    private $categoryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attributeRepository = Mockery::mock(AttributeRepository::class);
        $this->categoryRepository = Mockery::mock(CategoryRepository::class);

        $this->exporter = new Exporter(
            Mockery::mock(JobTrackBatchRepository::class),
            Mockery::mock(FlatItemBuffer::class),
            Mockery::mock(BagistoDataMapping::class),
            $this->attributeRepository,
            Mockery::mock(ProductRepository::class),
            $this->categoryRepository,
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

    private function lookup(string $method, string $code)
    {
        $reflection = new ReflectionMethod($this->exporter, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->exporter, $code);
    }

    public function test_it_reads_every_attribute_in_one_query()
    {
        $this->attributeRepository->shouldReceive('all')->once()->andReturn(new Collection([
            (object) ['code' => 'name', 'type' => 'text'],
            (object) ['code' => 'price', 'type' => 'price'],
        ]));

        $this->attributeRepository->shouldNotReceive('where');

        foreach (['name', 'price', 'name', 'weight'] as $code) {
            $this->lookup('attributeByCode', $code);
        }

        $this->assertSame('price', $this->lookup('attributeByCode', 'price')->type);
        $this->assertNull($this->lookup('attributeByCode', 'weight'));
    }

    public function test_it_looks_a_category_up_once_however_often_it_is_asked_for()
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('first')->once()->andReturn((object) ['code' => 'men']);

        $this->categoryRepository->shouldReceive('where')->once()->with('code', 'men')->andReturn($builder);

        foreach (range(1, 4) as $ignored) {
            $this->lookup('categoryByCode', 'men');
        }

        $this->assertSame('men', $this->lookup('categoryByCode', 'men')->code);
    }

    public function test_it_remembers_that_a_category_does_not_exist()
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('first')->once()->andReturnNull();

        $this->categoryRepository->shouldReceive('where')->once()->with('code', 'ghost')->andReturn($builder);

        $this->assertNull($this->lookup('categoryByCode', 'ghost'));
        $this->assertNull($this->lookup('categoryByCode', 'ghost'));
    }
}
