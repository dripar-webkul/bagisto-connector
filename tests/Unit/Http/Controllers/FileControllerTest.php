<?php

namespace Webkul\Bagisto\Tests\Unit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\BagistoImageFormat;
use Webkul\Bagisto\Enums\Export\DamFileType;

class FileControllerTest extends TestCase
{
    private function signedUrl(string $path, ?int $minutes = 60): string
    {
        return URL::temporarySignedRoute('bagisto.asset.fetch', now()->addMinutes($minutes), ['path' => $path]);
    }

    private function accepts(string $url): bool
    {
        return URL::hasValidSignature(Request::create($url));
    }

    public function test_it_signs_the_asset_url_it_hands_to_bagisto()
    {
        $this->assertStringContainsString('signature=', $this->signedUrl('assets/Root/front.jpg'));
    }

    public function test_it_accepts_the_url_it_signed()
    {
        $this->assertTrue($this->accepts($this->signedUrl('assets/Root/front.jpg')));
    }

    public function test_it_refuses_an_unsigned_url()
    {
        $this->assertFalse($this->accepts(route('bagisto.asset.fetch', ['path' => 'assets/Root/front.jpg'])));
    }

    public function test_it_refuses_a_path_swapped_after_signing()
    {
        $tampered = str_replace('front.jpg', 'secret-contract.pdf', $this->signedUrl('assets/Root/front.jpg'));

        $this->assertFalse($this->accepts($tampered));
    }

    public function test_it_refuses_an_expired_url()
    {
        $this->assertFalse($this->accepts($this->signedUrl('assets/Root/front.jpg', -1)));
    }

    public function test_only_image_assets_may_be_served()
    {
        $this->assertTrue(DamFileType::isImage('image'));

        foreach (['video', 'document', 'audio'] as $fileType) {
            $this->assertFalse(DamFileType::isImage($fileType));
        }
    }

    public function test_only_formats_bagisto_can_decode_may_be_served()
    {
        $this->assertTrue(BagistoImageFormat::accepts('assets/Root/front.jpg'));

        foreach (['assets/Root/logo.svg', 'assets/Root/manual.pdf', 'assets/Root/clip.mp4'] as $path) {
            $this->assertFalse(BagistoImageFormat::accepts($path));
        }
    }
}
