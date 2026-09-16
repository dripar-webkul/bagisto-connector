<?php

namespace Webkul\Bagisto\View\Composers;

use Illuminate\View\View;
use Webkul\Bagisto\Support\SkippedPanels;

class SkippedItemsComposer
{
    public function compose(View $view): void
    {
        $trackId = request()->route('batch_id') ?? request()->route('id');

        $view->with([
            'bagistoPanels'   => SkippedPanels::forJobTrack($trackId),
            'bagistoEndpoint' => $trackId
                ? route('admin.bagisto.job_track.skipped_items', ['trackId' => $trackId])
                : '',
        ]);
    }
}
