<?php

namespace Webkul\Bagisto\Tests\Unit\Traits;

use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\SkipReason;
use Webkul\Bagisto\Enums\Export\SkipScope;
use Webkul\Bagisto\Traits\SkippedItems;

class SkippedItemsTest extends TestCase
{
    private function collector(): object
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

    public function test_it_records_the_identifier_and_reason_for_a_skipped_item()
    {
        $collector = $this->collector();

        $collector->recordSkipped('shoe-1', SkipReason::MISSING_REQUIRED_FIELDS, ['price', 'weight']);

        $this->assertSame([
            [
                'scope'      => SkipScope::PRODUCT->value,
                'identifier' => 'shoe-1',
                'reason'     => SkipReason::MISSING_REQUIRED_FIELDS->value,
                'details'    => ['price', 'weight'],
            ],
        ], $collector->reasons());
    }

    public function test_it_records_an_excluded_file_against_the_product_it_belongs_to()
    {
        $collector = $this->collector();

        $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);

        $this->assertSame([
            [
                'scope'      => SkipScope::MEDIA->value,
                'identifier' => 'shoe-1',
                'reason'     => SkipReason::UNSUPPORTED_MEDIA_TYPE->value,
                'details'    => ['advert.mp4'],
            ],
        ], $collector->reasons());
    }

    public function test_it_leaves_the_skipped_count_alone_when_only_a_file_was_left_out()
    {
        $collector = $this->collector();

        $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);
        $collector->recordExcludedMedia('shoe-1', SkipReason::MEDIA_NOT_FOUND, ['gone.jpg']);

        $this->assertSame(0, $collector->skippedItemsCount);
        $this->assertCount(2, $collector->reasons());
    }

    public function test_it_keeps_one_entry_per_excluded_file()
    {
        $collector = $this->collector();

        $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);
        $collector->recordExcludedMedia('shoe-1', SkipReason::UNSUPPORTED_MEDIA_TYPE, ['advert.mp4']);

        $this->assertCount(1, $collector->reasons());
    }

    public function test_it_only_counts_product_level_skips_as_errors()
    {
        $this->assertTrue(SkipScope::PRODUCT->countsAsError());
        $this->assertFalse(SkipScope::MEDIA->countsAsError());
    }

    public function test_it_treats_an_entry_written_before_scopes_existed_as_a_product_skip()
    {
        $this->assertSame(SkipScope::PRODUCT, SkipScope::of(null));
        $this->assertSame(SkipScope::PRODUCT, SkipScope::of('nonsense'));
    }

    public function test_it_counts_every_skipped_item()
    {
        $collector = $this->collector();

        $collector->recordSkipped('a', SkipReason::MISSING_REQUIRED_FIELDS, ['price']);
        $collector->recordSkipped('b', SkipReason::UNSUPPORTED_TYPE);

        $this->assertSame(2, $collector->skippedItemsCount);
    }

    public function test_it_keeps_one_entry_per_identifier_and_reason()
    {
        $collector = $this->collector();

        $collector->recordSkipped('a', SkipReason::MISSING_REQUIRED_FIELDS, ['price']);
        $collector->recordSkipped('a', SkipReason::MISSING_REQUIRED_FIELDS, ['price']);

        $this->assertCount(1, $collector->reasons());
        $this->assertSame(1, $collector->skippedItemsCount);
    }

    public function test_it_describes_a_missing_field_skip_in_words_a_user_can_act_on()
    {
        $message = SkipReason::MISSING_REQUIRED_FIELDS->describe('shoe-1', ['price', 'weight']);

        $this->assertStringContainsString('shoe-1', $message);
        $this->assertStringContainsString('price', $message);
        $this->assertStringContainsString('weight', $message);
    }

    public function test_it_describes_every_reason_without_needing_details()
    {
        foreach (SkipReason::cases() as $reason) {
            $this->assertNotEmpty($reason->describe('sku-1'));
        }
    }
}
