<?php

namespace Webkul\Bagisto\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Bagisto\Support\SkippedPanels;

class SkippedItemsController extends Controller
{
    public function show(int $trackId): JsonResponse
    {
        return new JsonResponse([
            'panels' => SkippedPanels::forJobTrack($trackId),
        ]);
    }
}
