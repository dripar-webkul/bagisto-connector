<?php

namespace Webkul\Bagisto\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Bagisto\Enums\Export\BagistoImageFormat;
use Webkul\Bagisto\Enums\Export\DamFileType;

class FileController extends Controller
{
    protected const ASSET_MODEL = 'Webkul\\DAM\\Models\\Asset';

    protected const DIRECTORY_MODEL = 'Webkul\\DAM\\Models\\Directory';

    public function fetchAsset(string $path): Response
    {
        abort_if(! class_exists(self::ASSET_MODEL), 404);

        $asset = (self::ASSET_MODEL)::where('path', $path)->first();

        abort_if(! $asset, 404);

        abort_if(! DamFileType::isImage($asset->file_type), 404);

        abort_if(! BagistoImageFormat::accepts($asset->path), 404);

        $disk = (self::DIRECTORY_MODEL)::getAssetDisk();

        abort_if(! Storage::disk($disk)->exists($path), 404);

        return response(Storage::disk($disk)->get($path), 200)
            ->header('Content-Type', Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream');
    }
}
