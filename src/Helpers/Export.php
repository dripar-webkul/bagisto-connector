<?php

namespace Webkul\Bagisto\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\DataTransfer\Helpers\Export as BaseExport;

class Export extends BaseExport
{
    public function completed(): void
    {
        $grammar = DB::rawQueryGrammar();

        $summary = $this->jobTrackBatchRepository
            ->select(
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'created')} as DECIMAl)) AS created"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'updated')} as DECIMAl)) AS updated"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'processed')} as DECIMAl)) AS processed"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'skipped')} as DECIMAl)) AS skipped"),
            )
            ->where('job_track_id', $this->export->id)
            ->groupBy('job_track_id')
            ->first()?->toArray();

        $summary ??= [
            'processed' => 0,
            'created'   => 0,
            'updated'   => 0,
            'skipped'   => 0,
        ];

        $export = $this->jobTrackRepository->update([
            'state'        => self::STATE_COMPLETED,
            'summary'      => $summary,
            'completed_at' => now(),
        ], $this->export->id);

        $this->setExport($export);

        Event::dispatch('data_transfer.export.completed', $export);

        $this->jobLogger->info(trans('data_transfer::app.job.completed'));
    }

    public function cancel(): void
    {
        $grammar = DB::rawQueryGrammar();

        $summary = $this->jobTrackBatchRepository
            ->select(
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'processed')} as DECIMAL)) AS processed"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'created')} as DECIMAL)) AS created"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'updated')} as DECIMAL)) AS updated"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'skipped')} as DECIMAL)) AS skipped"),
            )
            ->where('job_track_id', $this->export->id)
            ->groupBy('job_track_id')
            ->first()?->toArray();

        $export = $this->jobTrackRepository->update([
            'state'        => self::STATE_CANCELLED,
            'summary'      => $summary ?? [],
            'completed_at' => now(),
        ], $this->export->id);

        $this->setExport($export);

        Event::dispatch('data_transfer.exports.cancelled', $export);
    }

    public function stats(string $state): array
    {
        $total = $this->export->batches()->count();
        $completed = $this->export->batches()->where('state', $state)->count();

        $progress = $total
            ? round($completed / $total * 100)
            : 0;

        $grammar = DB::rawQueryGrammar();

        $summary = $this->jobTrackBatchRepository
            ->select(
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'processed')} as DECIMAL)) AS processed"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'created')} as DECIMAL)) AS created"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'updated')} as DECIMAL)) AS updated"),
                DB::raw("SUM(CAST({$grammar->jsonExtract('summary', 'skipped')} as DECIMAL)) AS skipped"),
            )
            ->where('job_track_id', $this->export->id)
            ->where('state', $state)
            ->groupBy('job_track_id')
            ->first()
            ?->toArray();

        return [
            'batches' => [
                'total'     => $total,
                'completed' => $completed,
                'remaining' => $total - $completed,
            ],
            'progress' => $progress,
            'summary'  => $summary ?? [
                'processed' => 0,
                'created'   => 0,
                'updated'   => 0,
                'skipped'   => 0,
            ],
        ];
    }
}
