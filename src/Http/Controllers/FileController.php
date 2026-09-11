<?php

namespace Webkul\Bagisto\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\DAM\Models\Asset;
use Webkul\DAM\Models\Directory;

class FileController extends Controller
{
    public function fetchAsset(string $path): Response
    {
        $asset = Asset::where('path', $path)->first();

        abort_if(! $asset, 404);

        $disk = Directory::getAssetDisk();

        abort_if(! Storage::disk($disk)->exists($path), 404);

        return response(Storage::disk($disk)->get($path), 200)
            ->header('Content-Type', Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream');
    }
}
