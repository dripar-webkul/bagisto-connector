<?php

namespace Webkul\Bagisto\Tests\Unit\Config;

use Tests\TestCase;
use Webkul\Bagisto\Enums\Export\ProductFilter;
use Webkul\Bagisto\Enums\Export\ProductStatus;
use Webkul\Bagisto\Validators\JobInstances\Export\ProductJobValidator;

class ExportersConfigTest extends TestCase
{
    public function test_every_exporter_points_at_a_class_that_exists()
    {
        foreach ($this->bagistoExporters() as $key => $exporter) {
            foreach (['exporter', 'source', 'validator'] as $slot) {
                if (isset($exporter[$slot])) {
                    $this->assertTrue(class_exists($exporter[$slot]), "{$key}.{$slot} is missing");
                }
            }
        }
    }

    public function test_the_product_export_is_validated()
    {
        $this->assertSame(ProductJobValidator::class, config('exporters.bagisto_product.validator'));
    }

    public function test_the_scoped_exports_share_the_connector_filters()
    {
        foreach (['bagisto_attribute', 'bagisto_attribute_families'] as $key) {
            $this->assertSame(
                ['credentials', 'channel', 'locale', 'code'],
                array_column(config("exporters.{$key}.filters.fields"), 'name')
            );
        }
    }

    public function test_the_category_export_adds_a_category_picker()
    {
        $this->assertSame(
            ['credentials', 'channel', 'locale', 'code', 'category_codes'],
            array_column(config('exporters.bagisto_categories.filters.fields'), 'name')
        );

        $picker = collect(config('exporters.bagisto_categories.filters.fields'))
            ->firstWhere('name', ProductFilter::CATEGORY_CODES->value);

        $this->assertSame('category-tree', $picker['type']);
    }

    public function test_no_connector_filter_claims_a_core_scope_name()
    {
        foreach (array_keys($this->bagistoExporters()) as $key) {
            $names = array_column(config("exporters.{$key}.filters.fields"), 'name');

            foreach (ProductFilter::reservedCoreNames() as $reserved) {
                if ($key === 'bagisto_product' && $reserved === 'categories') {
                    continue;
                }

                $this->assertNotContains($reserved, $names, "{$key} must not declare '{$reserved}'");
            }
        }
    }

    public function test_the_cards_between_them_cover_every_connector_filter()
    {
        $this->assertSame(['credentials', 'type'], ProductFilter::credentialFields());
        $this->assertSame(['channel', 'locale', 'attribute_codes'], ProductFilter::scopeFields());
        $this->assertSame(['code', 'category_codes'], ProductFilter::filterFields());

        $this->assertSame(
            ProductFilter::connectorFields(),
            array_merge(
                ProductFilter::credentialFields(),
                ProductFilter::scopeFields(),
                ProductFilter::filterFields()
            )
        );

        $names = array_column(config('exporters.bagisto_product.filters.fields'), 'name');

        foreach (array_merge(ProductFilter::credentialFields(), ProductFilter::scopeFields()) as $field) {
            $this->assertContains($field, $names);
        }
    }

    public function test_the_fields_scoped_by_the_credential_are_the_ones_that_declare_it()
    {
        $this->assertSame(['channel', 'locale'], ProductFilter::scopedByCredential());

        $fields = collect(config('exporters.bagisto_product.filters.fields'));

        $declared = $fields
            ->filter(fn (array $field): bool => ($field['depends_on']['field'] ?? null) === ProductFilter::CREDENTIALS->value)
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame(ProductFilter::scopedByCredential(), $declared);
    }

    public function test_channel_and_locale_options_are_scoped_to_the_selected_credential()
    {
        foreach (['bagisto_product', 'bagisto_categories', 'bagisto_attribute', 'bagisto_attribute_families'] as $key) {
            $fields = collect(config("exporters.{$key}.filters.fields"));

            foreach ([ProductFilter::CHANNEL->value, ProductFilter::LOCALE->value] as $name) {
                $this->assertSame(
                    ['field' => ProductFilter::CREDENTIALS->value, 'as' => ProductFilter::CREDENTIALS->value],
                    $fields->firstWhere('name', $name)['depends_on'],
                    "{$key}.{$name} must be scoped by the credential"
                );
            }
        }
    }

    public function test_the_status_filter_offers_the_bagisto_status_values()
    {
        $this->assertSame(ProductStatus::values(), array_column($this->productField('status')['options'], 'value'));
    }

    public function test_identifier_filters_are_tag_inputs()
    {
        $this->assertSame('tags', $this->productField('sku')['type']);
        $this->assertSame('tags', config('exporters.bagisto_categories.filters.fields.3.type'));
    }

    public function test_connector_fields_match_the_filters_the_product_export_declares()
    {
        $names = array_column(config('exporters.bagisto_product.filters.fields'), 'name');

        $notOnProducts = [ProductFilter::CODE->value, ProductFilter::CATEGORY_CODES->value];

        foreach (array_diff(ProductFilter::connectorFields(), $notOnProducts) as $field) {
            $this->assertContains($field, $names);
        }
    }

    private function bagistoExporters(): array
    {
        return array_filter(
            config('exporters'),
            fn ($key): bool => str_starts_with($key, 'bagisto_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function productField(string $name): array
    {
        $fields = config('exporters.bagisto_product.filters.fields');

        return collect($fields)->firstWhere('name', $name) ?? [];
    }
}
