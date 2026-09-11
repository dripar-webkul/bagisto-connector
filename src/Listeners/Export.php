<?php

namespace Webkul\Bagisto\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Bagisto\Enums\Export\CacheType;
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

        if (in_array($export->entity_type, $types)) {
            Cache::forget(CacheType::CREDENTIAL->value);
            Cache::forget(CacheType::PRODUCT_JOB_FILTERS->value);
            Cache::forget(CacheType::CATEGORY_JOB_FILTERS->value);
            Cache::forget(CacheType::ADDITIONAL_INFO->value);
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

        $this->jobTrackRepository->update([
            'summary' => $summary ?: [
                'processed' => 0,
                'created'   => 0,
                'updated'   => 0,
                'skipped'   => 0,
            ],
        ], $export->id);
    }
}
