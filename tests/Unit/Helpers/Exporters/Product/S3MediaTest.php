<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Illuminate\Support\Facades\Config;
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
use Webkul\DataTransfer\Helpers\Sources\Export\ProductSource;
use Webkul\DataTransfer\Jobs\Export\File\FlatItemBuffer;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;
use Webkul\Product\Repositories\ProductRepository;

class S3MediaTest extends TestCase
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

    private function invoke(string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($this->exporter, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->exporter, $args);
    }

    private function useS3(string $visibility, ?string $bucketUrl = null): void
    {
        Config::set('filesystems.default', 's3');
        Config::set('filesystems.disks.s3.visibility', $visibility);
        Config::set('filesystems.disks.s3.url', $bucketUrl);
    }

    public function test_it_serves_media_from_the_bucket_url_of_a_public_bucket()
    {
        $this->useS3('public', 'https://cdn.example.com/bucket');

        $this->assertSame(
            'https://cdn.example.com/bucket/product/1/front.jpg',
            $this->invoke('resolveMediaUrl', ['product/1/front.jpg'])
        );
    }

    public function test_it_falls_back_to_the_disk_when_no_bucket_url_is_configured()
    {
        Storage::fake('s3');
        Config::set('filesystems.default', 's3');

        $this->assertIsString($this->invoke('resolveMediaUrl', ['product/1/front.jpg']));
    }

    public function test_it_presigns_media_on_a_private_bucket()
    {
        $this->useS3('private');
        Config::set('bagisto-media.temporary_url_ttl', 60);

        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->withArgs(function ($path, $expiry) {
                $minutes = (int) round(now()->diffInMinutes($expiry));

                return $path === 'product/1/front.jpg' && $minutes >= 59 && $minutes <= 60;
            })
            ->andReturn('https://bucket.s3.amazonaws.com/product/1/front.jpg?signature=abc');

        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->assertStringContainsString(
            'signature=abc',
            $this->invoke('resolveMediaUrl', ['product/1/front.jpg'])
        );
    }

    public function test_it_lets_the_presigned_lifetime_be_configured()
    {
        Config::set('bagisto-media.temporary_url_ttl', 15);

        $this->assertSame(15, $this->invoke('temporaryUrlTtl'));
    }

    public function test_it_ignores_a_lifetime_that_would_expire_immediately()
    {
        Config::set('bagisto-media.temporary_url_ttl', 0);

        $this->assertSame(60, $this->invoke('temporaryUrlTtl'));
    }

    public function test_it_addresses_dam_assets_on_s3_by_bucket_url()
    {
        $this->useS3('public', 'https://cdn.example.com/bucket');

        $this->assertSame(
            'https://cdn.example.com/bucket/assets/Root/front.jpg',
            $this->invoke('makeDamPublicUrl', ['assets/Root/front.jpg'])
        );
    }

    public function test_it_escapes_a_dam_path_that_carries_spaces()
    {
        $this->useS3('public', 'https://demo.s3.ap-south-1.amazonaws.com');

        $url = $this->invoke('makeDamPublicUrl', ['assets/Root/Product Photography/wallpaper.jpg']);

        $this->assertSame(
            'https://demo.s3.ap-south-1.amazonaws.com/assets/Root/Product%20Photography/wallpaper.jpg',
            $url
        );
        $this->assertNotFalse(filter_var($url, FILTER_VALIDATE_URL));
    }

    public function test_it_escapes_product_media_paths_too()
    {
        Storage::fake('s3');
        $this->useS3('public', 'https://demo.s3.ap-south-1.amazonaws.com');

        Storage::disk('s3')->put('product/13/image/front view.jpg', 'binary');

        $this->assertSame(
            'https://demo.s3.ap-south-1.amazonaws.com/product/13/image/front%20view.jpg',
            $this->invoke('getExistingFilePath', ['product/13/image/front view.jpg'])
        );
    }

    public function test_it_leaves_a_path_that_needs_no_escaping_alone()
    {
        $this->useS3('public', 'https://demo.s3.ap-south-1.amazonaws.com');

        $this->assertSame(
            'https://demo.s3.ap-south-1.amazonaws.com/assets/Root/wallpaper.jpg',
            $this->invoke('makeDamPublicUrl', ['assets/Root/wallpaper.jpg'])
        );
    }

    public function test_it_escapes_local_media_paths_as_well()
    {
        Storage::fake('local');
        Config::set('filesystems.default', 'local');

        Storage::put('product/13/image/front view.jpg', 'binary');

        $this->assertStringContainsString(
            'front%20view.jpg',
            $this->invoke('getExistingFilePath', ['product/13/image/front view.jpg'])
        );
    }

    public function test_it_still_serves_dam_assets_through_the_fetch_route_without_s3()
    {
        Config::set('filesystems.default', 'local');

        $url = $this->invoke('makeDamPublicUrl', ['assets/Root/front.jpg']);

        $this->assertStringContainsString('/bagisto/asset/assets/Root/front.jpg', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_it_reads_product_media_back_from_the_bucket()
    {
        Storage::fake('s3');
        $this->useS3('public', 'https://cdn.example.com/bucket');

        Storage::disk('s3')->put('product/1/front.jpg', 'binary');

        $this->assertSame(
            'https://cdn.example.com/bucket/product/1/front.jpg',
            $this->invoke('getExistingFilePath', ['product/1/front.jpg'])
        );
    }

    public function test_it_leaves_out_media_that_is_missing_from_the_bucket()
    {
        Storage::fake('s3');
        $this->useS3('public', 'https://cdn.example.com/bucket');

        $this->assertNull($this->invoke('getExistingFilePath', ['product/1/gone.jpg']));
    }
}
