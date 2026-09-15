<?php

namespace Webkul\Bagisto\Traits;

use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Export\SkipScope;

trait SkippedItems
{
    protected array $skippedItems = [];

    public function recordSkipped(string $identifier, SkipReason $reason, array $details = []): void
    {
        if ($this->addSkippedEntry(SkipScope::PRODUCT, $identifier, $reason, $details)) {
            $this->skippedItemsCount++;
        }
    }

    public function recordExcludedMedia(string $identifier, SkipReason $reason, array $details = []): void
    {
        $this->addSkippedEntry(SkipScope::MEDIA, $identifier, $reason, $details);
    }

    public function getSkippedItems(): array
    {
        return $this->skippedItems;
    }

    private function addSkippedEntry(SkipScope $scope, string $identifier, SkipReason $reason, array $details): bool
    {
        $entry = [
            'scope'      => $scope->value,
            'identifier' => $identifier,
            'reason'     => $reason->value,
            'details'    => array_values($details),
        ];

        if (in_array($entry, $this->skippedItems, true)) {
            return false;
        }

        $this->skippedItems[] = $entry;

        $this->jobLogger?->warning($reason->describe($identifier, $details));

        return true;
    }
}
