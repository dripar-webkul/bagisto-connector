<?php

namespace Webkul\Bagisto\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Bagisto\Enums\Export\CacheType;
use Webkul\Bagisto\Enums\Export\SkipScope;
use Webkul\DataTransfer\Repositories\JobTrackBatchRepository;
use Webkul\DataTransfer\Repositories\JobTrackRepository;

class Export
{
    public function __construct(
        protected JobTrackBatchRepository $jobTrackBatchRepository,
        protected JobTrackRepository $jobTrackRepository,
    ) {}

    public function afterUpdate($export): void
    {
        $types = [
            'bagisto_product',
            'bagisto_categories',
            'bagisto_attribute',
            'bagisto_attribute_families',
        ];

        if (! in_array($export->entity_type, $types)) {
            return;
        }

        $credentialId = data_get($export, 'filters.credentials');

        foreach ([
            CacheType::CREDENTIAL,
            CacheType::ADDITIONAL_INFO,
            CacheType::ATTRIBUTE_MAPPING,
            CacheType::CATEGORY_FIELD_MAPPING,
            CacheType::BAGISTO_API_HTTP,
        ] as $cacheType) {
            Cache::forget($cacheType->forCredential($credentialId));
        }
    }

    public function afterCompleted($export): void
    {
        $types = [
            'bagisto_product',
            'bagisto_categories',
            'bagisto_attribute',
            'bagisto_attribute_families',
        ];

        if (! in_array(data_get($export, 'jobInstance.entity_type'), $types)) {
            return;
        }

        $grammar = DB::rawQueryGrammar();

        $summary = $this->jobTrackBatchRepository
            ->select(
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'created')} as DECIMAl)) AS created"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'updated')} as DECIMAl)) AS updated"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'processed')} as DECIMAl)) AS processed"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'skipped')} as DECIMAl)) AS skipped"),
            )
            ->where('job_track_id', $export->id)
            ->groupBy('job_track_id')
            ->first()?->toArray();

        $skipped = $this->collectSkippedItems($export->id);

        $this->jobTrackRepository->update([
            'summary' => $summary ?: [
                'processed' => 0,
                'created'   => 0,
                'updated'   => 0,
                'skipped'   => 0,
            ],
            'errors'       => $skipped,
            'errors_count' => count(array_filter(
                $skipped,
                fn ($entry) => SkipScope::of($entry['scope'] ?? null)->countsAsError()
            )),
        ], $export->id);
    }

    /**
     * @return array<int, array{identifier: string, reason: string, details: array<int, string>}>
     */
    protected function collectSkippedItems(int $jobTrackId): array
    {
        $skipped = [];

        foreach ($this->jobTrackBatchRepository->findWhere(['job_track_id' => $jobTrackId]) as $batch) {
            foreach ($batch->summary['skipped_reasons'] ?? [] as $entry) {
                if (! in_array($entry, $skipped, true)) {
                    $skipped[] = $entry;
                }
            }
        }

        return $skipped;
    }
}
