<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
use Webkul\DAM\Models\Asset;
use Webkul\DAM\Repositories\AssetRepository;
use Webkul\DataTransfer\Helpers\Sources\Export\ProductSource;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;
use Webkul\Product\Repositories\ProductRepository;

class MediaOrderTest extends TestCase
{
    private Exporter $exporter;

    private $attributeRepository;

    private $assetRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attributeRepository = Mockery::mock(AttributeRepository::class);
        $this->assetRepository = Mockery::mock(AssetRepository::class);

        $this->exporter = new Exporter(
            Mockery::mock(JobTrackBatchRepository::class),
            Mockery::mock(FlatItemBuffer::class),
            Mockery::mock(BagistoDataMapping::class),
            $this->attributeRepository,
            Mockery::mock(ProductRepository::class),
            Mockery::mock(CategoryRepository::class),
            Mockery::mock(AttributeOptionRepository::class),
            Mockery::mock(AttributeMappingRepository::class),
            Mockery::mock(ChannelRepository::class),
            Mockery::mock(CredentialRepository::class),
            Mockery::mock(ProductSource::class),
            $this->assetRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function expectAssetLookup(array $rowsInDatabaseOrder): void
    {
        $this->attributeRepository->shouldReceive('where')->with('code', 'gallery')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn(
            (object) ['type' => Asset::ASSET_ATTRIBUTE_TYPE]
        );

        $this->assetRepository->shouldReceive('findWhereIn')->andReturn(
            new Collection(array_map(
                fn ($row) => (object) ($row + ['file_type' => 'image']),
                $rowsInDatabaseOrder
            ))
        );
    }

    private function resolve(array $mergedFields): array
    {
        $method = new \ReflectionMethod($this->exporter, 'resolveDamAssetPaths');
        $method->setAccessible(true);
        $method->invokeArgs($this->exporter, [&$mergedFields]);

        return $mergedFields;
    }

    public function test_it_keeps_the_order_the_assets_were_arranged_in()
    {
        $this->expectAssetLookup([
            ['id' => 7, 'path' => 'dam/back.jpg'],
            ['id' => 9, 'path' => 'dam/front.jpg'],
            ['id' => 12, 'path' => 'dam/side.jpg'],
        ]);

        $result = $this->resolve(['gallery' => [9, 12, 7]]);

        $this->assertSame(['dam/front.jpg', 'dam/side.jpg', 'dam/back.jpg'], $result['gallery']);
    }

    public function test_it_keeps_the_order_for_a_comma_separated_value()
    {
        $this->expectAssetLookup([
            ['id' => 7, 'path' => 'dam/back.jpg'],
            ['id' => 9, 'path' => 'dam/front.jpg'],
        ]);

        $result = $this->resolve(['gallery' => '9,7']);

        $this->assertSame(['dam/front.jpg', 'dam/back.jpg'], $result['gallery']);
    }

    public function test_it_skips_ids_that_no_longer_resolve_to_an_asset()
    {
        $this->expectAssetLookup([
            ['id' => 9, 'path' => 'dam/front.jpg'],
        ]);

        $result = $this->resolve(['gallery' => [9, 404]]);

        $this->assertSame('dam/front.jpg', $result['gallery']);
    }

    public function test_it_drops_the_field_when_nothing_resolves()
    {
        $this->expectAssetLookup([]);

        $this->assertArrayNotHasKey('gallery', $this->resolve(['gallery' => [404]]));
    }

    public function test_it_leaves_out_assets_that_are_not_images()
    {
        $this->attributeRepository->shouldReceive('where')->with('code', 'gallery')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn(
            (object) ['type' => Asset::ASSET_ATTRIBUTE_TYPE]
        );

        $this->assetRepository->shouldReceive('findWhereIn')->andReturn(new Collection([
            (object) ['id' => 9, 'path' => 'dam/front.jpg', 'file_type' => 'image'],
            (object) ['id' => 10, 'path' => 'dam/manual.pdf', 'file_type' => 'document'],
            (object) ['id' => 11, 'path' => 'dam/advert.mp3', 'file_type' => 'audio'],
            (object) ['id' => 12, 'path' => 'dam/back.jpg', 'file_type' => 'image'],
        ]));

        $result = $this->resolve(['gallery' => [9, 10, 11, 12]]);

        $this->assertSame(['dam/front.jpg', 'dam/back.jpg'], $result['gallery']);
    }

    public function test_it_drops_the_field_when_no_asset_is_an_image()
    {
        $this->attributeRepository->shouldReceive('where')->with('code', 'gallery')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn(
            (object) ['type' => Asset::ASSET_ATTRIBUTE_TYPE]
        );

        $this->assetRepository->shouldReceive('findWhereIn')->andReturn(new Collection([
            (object) ['id' => 10, 'path' => 'dam/manual.pdf', 'file_type' => 'document'],
        ]));

        $this->assertArrayNotHasKey('gallery', $this->resolve(['gallery' => [10]]));
    }

    public function test_it_leaves_out_image_formats_bagisto_cannot_decode()
    {
        $this->attributeRepository->shouldReceive('where')->with('code', 'gallery')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn(
            (object) ['type' => Asset::ASSET_ATTRIBUTE_TYPE]
        );

        $this->assetRepository->shouldReceive('findWhereIn')->andReturn(new Collection([
            (object) ['id' => 9, 'path' => 'dam/front.jpg', 'file_type' => 'image'],
            (object) ['id' => 10, 'path' => 'dam/logo.svg', 'file_type' => 'image'],
            (object) ['id' => 11, 'path' => 'dam/back.png', 'file_type' => 'image'],
        ]));

        $result = $this->resolve(['gallery' => [9, 10, 11]]);

        $this->assertSame(['dam/front.jpg', 'dam/back.png'], $result['gallery']);
    }

    public function test_it_combines_image_sources_in_the_order_they_were_mapped()
    {
        $this->mapImagesTo(['lifestyle_shot', 'packshot']);

        $images = $this->combine(['packshot' => 'a.jpg', 'lifestyle_shot' => 'b.jpg'])['images'];

        $this->assertLessThan(
            strpos($images, 'a.jpg'),
            strpos($images, 'b.jpg'),
            "the mapped order should put b.jpg first, got [{$images}]"
        );
    }

    public function test_it_still_combines_sources_that_were_never_mapped()
    {
        $this->mapImagesTo(['lifestyle_shot']);

        $images = $this->combine(['packshot' => 'a.jpg', 'lifestyle_shot' => 'b.jpg'])['images'];

        $this->assertStringContainsString('a.jpg', $images);
        $this->assertLessThan(strpos($images, 'a.jpg'), strpos($images, 'b.jpg'));
    }

    private function mapImagesTo(array $codes): void
    {
        $property = new \ReflectionProperty($this->exporter, 'mappingAttributes');
        $property->setAccessible(true);
        $property->setValue($this->exporter, [
            'standard_attribute' => (object) ['mapped_value' => [], 'fixed_value' => []],
            'image_attribute'    => (object) ['mapped_value' => ['images' => $codes], 'fixed_value' => []],
        ]);
    }

    private function combine(array $mergedFields): array
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        foreach ($mergedFields as $path) {
            Storage::put($path, 'binary');
        }

        $this->attributeRepository->shouldReceive('where')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn((object) ['type' => 'image']);

        $method = new \ReflectionMethod($this->exporter, 'handleAttributeType');
        $method->setAccessible(true);
        $method->invokeArgs($this->exporter, [&$mergedFields, true, 'default']);

        return $mergedFields;
    }
}
