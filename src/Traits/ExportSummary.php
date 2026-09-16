<?php

namespace Webkul\Bagisto\Traits;

trait ExportSummary
{
    public function getUpdatedItemsCount(): int
    {
        $count = $this->export->summary['updated'] ?? 0;

        return $count + $this->updatedItemsCount;
    }

    public function updateBatchState(int $id, string $state): void
    {
        if (method_exists($this, 'heartbeat')) {
            $this->heartbeat(true);
        }

        $processed = $this->getCreatedItemsCount() + $this->getUpdatedItemsCount() - $this->getSkippedtemsCount();

        $this->exportBatchRepository->update([
            'state'   => $state,
            'summary' => [
                'processed'       => $processed < 0 ? 0 : $processed,
                'created'         => $this->getCreatedItemsCount(),
                'updated'         => $this->getUpdatedItemsCount(),
                'skipped'         => $this->getSkippedtemsCount(),
                'skipped_reasons' => method_exists($this, 'getSkippedItems') ? $this->getSkippedItems() : [],
            ],
        ], $id);
    }
}
