<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Tests\TestCase;
use Webkul\Bagisto\Helpers\Exporters\Product\ProductExportFilter;
use Webkul\Category\Models\Category;
use Webkul\Core\Models\Channel;
use Webkul\Product\Models\Product;

class ProductExportFilterTest extends TestCase
{
    private ProductExportFilter $filter;

    private string $channel;

    private string $inChannel;

    private string $outOfChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = resolve(ProductExportFilter::class);

        $this->buildChannelTree();
    }

    private function buildChannelTree(): void
    {
        $suffix = uniqid();

        $root = Category::create(['code' => 'scoped_root_'.$suffix]);

        $this->inChannel = 'in_channel_'.$suffix;
        $this->outOfChannel = 'out_of_channel_'.$suffix;

        Category::create(['code' => $this->inChannel, 'parent_id' => $root->id]);
        Category::create(['code' => $this->outOfChannel]);

        $channel = Channel::first()->replicate();
        $channel->code = 'scoped_channel_'.$suffix;
        $channel->root_category_id = $root->id;
        $channel->save();

        $this->channel = $channel->code;
    }

    public function test_apply_to_query_drops_a_category_outside_the_channel()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, [
            'channel'    => $this->channel,
            'categories' => $this->inChannel.','.$this->outOfChannel,
        ]);

        $this->assertContains('"'.$this->inChannel.'"', $query->getBindings());
        $this->assertNotContains('"'.$this->outOfChannel.'"', $query->getBindings());
    }

    public function test_apply_to_query_exports_nothing_when_the_channel_reaches_no_root()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, [
            'channel'    => 'channel_that_does_not_exist',
            'categories' => $this->inChannel,
        ]);

        $this->assertStringContainsString('1 = 0', $query->toSql());
        $this->assertNotContains('"'.$this->inChannel.'"', $query->getBindings());
    }

    public function test_apply_to_query_exports_nothing_when_no_category_is_in_the_channel()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, [
            'channel'    => $this->channel,
            'categories' => $this->outOfChannel,
        ]);

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }

    public function test_apply_to_query_leaves_the_query_alone_when_no_category_is_picked()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, ['channel' => $this->channel]);

        $this->assertStringNotContainsString('categories', $query->toSql());
        $this->assertStringNotContainsString('1 = 0', $query->toSql());
    }

    public function test_status_value_reads_the_bagisto_status_codes()
    {
        $this->assertTrue($this->filter->statusValue(['status' => 't']));
        $this->assertFalse($this->filter->statusValue(['status' => 'f']));
        $this->assertNull($this->filter->statusValue(['status' => 'all']));
    }

    public function test_status_value_is_null_when_the_filter_is_missing_or_unknown()
    {
        $this->assertNull($this->filter->statusValue([]));
        $this->assertNull($this->filter->statusValue(['status' => 'enable']));
    }

    public function test_apply_to_query_constrains_the_product_type()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, ['type' => ['simple', 'configurable']]);

        $this->assertStringContainsString('"type" in', str_replace('`', '"', $query->toSql()));
        $this->assertSame(['simple', 'configurable'], $query->getBindings());
    }

    public function test_apply_to_query_ignores_an_empty_type()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, ['type' => '']);

        $this->assertSame([], $query->getBindings());
    }

    public function test_apply_to_query_applies_the_bagisto_status_code()
    {
        $query = Product::query();

        $this->filter->applyToQuery($query, ['status' => 'f']);

        $this->assertSame([false], $query->getBindings());
    }

    public function test_to_core_scope_reads_the_filter_names_saved_before_the_rename()
    {
        $this->assertSame(
            ['channels' => ['default'], 'locales' => ['en_US']],
            $this->toCoreScope(['channel' => ['default'], 'locale' => ['en_US']])
        );
    }

    public function test_to_core_scope_reads_the_current_filter_names()
    {
        $this->assertSame(
            ['channels' => ['default', 'b2b'], 'locales' => ['en_US']],
            $this->toCoreScope(['channels' => ['default', 'b2b'], 'locales' => 'en_US'])
        );
    }

    public function test_to_core_scope_is_empty_when_nothing_was_selected()
    {
        $this->assertSame(['channels' => [], 'locales' => []], $this->toCoreScope([]));
    }

    private function toCoreScope(array $filters): array
    {
        $method = new \ReflectionMethod($this->filter, 'toCoreScope');
        $method->setAccessible(true);

        return $method->invoke($this->filter, $filters);
    }
}
