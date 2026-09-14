<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Rules\AttributeTypes;
use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Export\SkipScope;
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

class ExcludedMediaTest extends TestCase
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

    private function damAssets(array $rows): void
    {
        $this->attributeRepository->shouldReceive('where')->with('code', 'gallery')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn(
            (object) ['type' => Asset::ASSET_ATTRIBUTE_TYPE]
        );

        $this->assetRepository->shouldReceive('findWhereIn')->andReturn(
            new Collection(array_map(fn ($row) => (object) $row, $rows))
        );
    }

    private function resolveDam(array $mergedFields, string $sku = 'shoe-1'): array
    {
        $method = new \ReflectionMethod($this->exporter, 'resolveDamAssetPaths');
        $method->setAccessible(true);
        $method->invokeArgs($this->exporter, [&$mergedFields, $sku]);

        return $mergedFields;
    }

    private function recorded(): array
    {
        return $this->exporter->getSkippedItems();
    }

    public function test_it_reports_a_dam_asset_that_is_not_an_image()
    {
        $this->damAssets([
            ['id' => 9, 'path' => 'dam/front.jpg', 'file_name' => 'front.jpg', 'file_type' => 'image'],
            ['id' => 10, 'path' => 'dam/advert.mp4', 'file_name' => 'advert.mp4', 'file_type' => 'video'],
        ]);

        $this->resolveDam(['gallery' => [9, 10]]);

        $this->assertSame([[
            'scope'      => SkipScope::MEDIA->value,
            'identifier' => 'shoe-1',
            'reason'     => SkipReason::UNSUPPORTED_MEDIA_TYPE->value,
            'details'    => ['advert.mp4'],
        ]], $this->recorded());
    }

    public function test_it_reports_a_dam_image_in_a_format_bagisto_cannot_decode()
    {
        $this->damAssets([
            ['id' => 9, 'path' => 'dam/logo.svg', 'file_name' => 'logo.svg', 'file_type' => 'image'],
        ]);

        $this->resolveDam(['gallery' => [9]]);

        $this->assertSame(SkipReason::UNSUPPORTED_IMAGE_FORMAT->value, $this->recorded()[0]['reason']);
        $this->assertSame(['logo.svg'], $this->recorded()[0]['details']);
    }

    public function test_it_reports_a_dam_asset_that_no_longer_exists()
    {
        $this->damAssets([]);

        $this->resolveDam(['gallery' => [404]]);

        $this->assertSame(SkipReason::MEDIA_NOT_FOUND->value, $this->recorded()[0]['reason']);
        $this->assertSame(['#404'], $this->recorded()[0]['details']);
    }

    public function test_it_says_nothing_when_every_asset_goes_through()
    {
        $this->damAssets([
            ['id' => 9, 'path' => 'dam/front.jpg', 'file_name' => 'front.jpg', 'file_type' => 'image'],
        ]);

        $this->resolveDam(['gallery' => [9]]);

        $this->assertSame([], $this->recorded());
    }

    public function test_it_reports_a_gallery_file_missing_from_storage()
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $this->attributeRepository->shouldReceive('where')->andReturnSelf();
        $this->attributeRepository->shouldReceive('first')->andReturn(
            (object) ['type' => AttributeTypes::GALLERY_ATTRIBUTE_TYPE]
        );

        $fields = ['gallery' => 'product/1/gone.jpg'];

        $method = new \ReflectionMethod($this->exporter, 'handleAttributeType');
        $method->setAccessible(true);
        $method->invokeArgs($this->exporter, [&$fields, true, 'default', 'shoe-1']);

        $this->assertArrayNotHasKey('gallery', $fields);
        $this->assertSame(SkipReason::MEDIA_NOT_FOUND->value, $this->recorded()[0]['reason']);
        $this->assertSame(['gone.jpg'], $this->recorded()[0]['details']);
    }

    public function test_excluded_media_never_makes_a_product_look_skipped()
    {
        $this->damAssets([
            ['id' => 10, 'path' => 'dam/advert.mp4', 'file_name' => 'advert.mp4', 'file_type' => 'video'],
        ]);

        $this->resolveDam(['gallery' => [10]]);

        $this->assertSame(0, $this->exporter->getSkippedtemsCount());
    }
}
