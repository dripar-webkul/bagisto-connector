<?php

namespace Webkul\Bagisto\Traits;

use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Export\SkipScope;

trait SkippedItems
{
    /**
     * @var array<int, array{scope: string, identifier: string, reason: string, details: array<int, string>}>
     */
    protected array $skippedItems = [];

    /**
     * @param  array<int, string>  $details
     */
    public function recordSkipped(string $identifier, SkipReason $reason, array $details = []): void
    {
        if ($this->addSkippedEntry(SkipScope::PRODUCT, $identifier, $reason, $details)) {
            $this->skippedItemsCount++;
        }
    }

    /**
     * @param  array<int, string>  $details
     */
    public function recordExcludedMedia(string $identifier, SkipReason $reason, array $details = []): void
    {
        $this->addSkippedEntry(SkipScope::MEDIA, $identifier, $reason, $details);
    }

    /**
     * @return array<int, array{scope: string, identifier: string, reason: string, details: array<int, string>}>
     */
    public function getSkippedItems(): array
    {
        return $this->skippedItems;
    }

    /**
     * @param  array<int, string>  $details
     */
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
