<?php

namespace Webkul\Bagisto\Tests\Unit\Helpers\Exporters\Product;

use Tests\TestCase;
use Webkul\Bagisto\Helpers\Exporters\Product\ProductExportFilter;
use Webkul\Product\Models\Product;

class ProductExportFilterTest extends TestCase
{
    private ProductExportFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = resolve(ProductExportFilter::class);
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
