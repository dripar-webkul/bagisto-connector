<?php

namespace Webkul\Bagisto\View\Composers;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Export\SkipScope;
use Webkul\DataTransfer\Models\JobTrackProxy;

class SkippedItemsComposer
{
    public function compose(View $view): void
    {
        $entries = $this->entries();

        $skipped = $entries->get(SkipScope::PRODUCT->value, new Collection);
        $excluded = $entries->get(SkipScope::MEDIA->value, new Collection);

        $view->with('bagistoPanels', array_values(array_filter([
            $this->panel('skipped', 'red', $skipped, $skipped->pluck('identifier')->unique()->count()),
            $this->panel('excluded', 'yellow', $excluded, $excluded->count()),
        ])));
    }

    protected function entries(): Collection
    {
        $trackId = request()->route('batch_id') ?? request()->route('id');

        $jobTrack = $trackId ? JobTrackProxy::find($trackId) : null;

        return Collection::wrap($jobTrack?->errors ?? [])
            ->filter(fn ($entry): bool => is_array($entry) && isset($entry['identifier'], $entry['reason']))
            ->groupBy(fn (array $entry): string => SkipScope::of($entry['scope'] ?? null)->value);
    }

    protected function panel(string $key, string $tone, Collection $rows, int $count): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'key'         => $key,
            'tone'        => $tone,
            'heading'     => trans("bagisto::app.bagisto.export.skipped.{$key}-heading", ['count' => $count]),
            'description' => trans("bagisto::app.bagisto.export.skipped.{$key}-description"),
            'reason'      => trans("bagisto::app.bagisto.export.skipped.{$key}-reason"),
            'rows'        => $rows->map(fn (array $row): array => [
                'identifier' => $row['identifier'],
                'reason'     => SkipReason::tryFrom($row['reason'])
                    ?->describe($row['identifier'], $row['details'] ?? [])
                    ?? $row['reason'],
            ])->values()->all(),
        ];
    }
}
