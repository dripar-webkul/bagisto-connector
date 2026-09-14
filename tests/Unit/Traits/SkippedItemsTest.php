<?php

use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Export\SkipScope;
use Webkul\Bagisto\Traits\SkippedItems;

uses(TestCase::class);

function skipCollector(): object
{
    return new class
    {
        use SkippedItems;

        public int $skippedItemsCount = 0;

        public $jobLogger;

        public function reasons(): array
        {
            return $this->skippedItems;
        }
    };
}

it('records the identifier and reason for a skipped item', function () {
    $collector = skipCollector();

    $collector->recordSkipped('shoe-1', SkipReason::MISSING_REQUIRED_FIELDS, ['price', 'weight']);

    expect($collector->reasons())->toBe([
        [
            'scope'      => SkipScope::PRODUCT->value,
            'identifier' => 'shoe-1',
            'reason'     => SkipReason::MISSING_REQUIRED_FIELDS->value,
            'details'    => ['price', 'weight'],
        ],
    ]);
});

it('records an excluded file against the product it belongs to', function () {
    $collector = skipCollector();

    $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);

    expect($collector->reasons())->toBe([
        [
            'scope'      => SkipScope::MEDIA->value,
            'identifier' => 'shoe-1',
            'reason'     => SkipReason::UNSUPPORTED_MEDIA_TYPE->value,
            'details'    => ['advert.mp4'],
        ],
    ]);
});

it('leaves the skipped count alone when only a file was left out', function () {
    $collector = skipCollector();

    $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);
    $collector->recordExcludedMedia('shoe-1', SkipReason::MEDIA_NOT_FOUND, ['gone.jpg']);

    expect($collector->skippedItemsCount)->toBe(0)
        ->and($collector->reasons())->toHaveCount(2);
});

it('keeps one entry per excluded file', function () {
    $collector = skipCollector();

    $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);
    $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);

    expect($collector->reasons())->toHaveCount(1);
});

it('only counts product level skips as errors', function () {
    expect(SkipScope::PRODUCT->countsAsError())->toBeTrue()
        ->and(SkipScope::MEDIA->countsAsError())->toBeFalse();
});

it('treats an entry written before scopes existed as a product skip', function () {
    expect(SkipScope::of(null))->toBe(SkipScope::PRODUCT)
        ->and(SkipScope::of('nonsense'))->toBe(SkipScope::PRODUCT);
});

it('counts every skipped item', function () {
    $collector = skipCollector();

    $collector->recordSkipped('a', SkipReason::MISSING_REQUIRED_FIELDS, ['price']);
    $collector->recordSkipped('b', SkipReason::UNSUPPORTED_TYPE);

    expect($collector->skippedItemsCount)->toBe(2);
});

it('keeps one entry per identifier and reason', function () {
    $collector = skipCollector();

    $collector->recordSkipped('a', SkipReason::MISSING_REQUIRED_FIELDS, ['price']);
    $collector->recordSkipped('a', SkipReason::MISSING_REQUIRED_FIELDS, ['price']);

    expect($collector->reasons())->toHaveCount(1)
        ->and($collector->skippedItemsCount)->toBe(1);
});

it('describes a missing field skip in words a user can act on', function () {
    $message = SkipReason::MISSING_REQUIRED_FIELDS->describe('shoe-1', ['price', 'weight']);

    expect($message)->toContain('shoe-1')
        ->and($message)->toContain('price')
        ->and($message)->toContain('weight');
});

it('describes every reason without needing details', function () {
    foreach (SkipReason::cases() as $reason) {
        expect($reason->describe('sku-1'))->toBeString()->not->toBeEmpty();
    }
});
